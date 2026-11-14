<?php

namespace App\Services;

use App\Enums\CashHandoverStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\DailyAttendance;
use App\Models\Order;
use App\Models\TechnicianOffDay;
use App\Models\TeknisiExpense;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Revisi 14 Nov 2026 poin 21 — crosscheck harian 4 kelengkapan administrasi teknisi
 * sebelum dana ditransfer: (1) absensi masuk & pulang, (2) dokumentasi pengerjaan,
 * (3) laporan penggunaan dana, (4) laporan setoran tunai.
 *
 * Status tiap butir: 'ok' | 'kurang' | 'na' (tidak berlaku, mis. hari OFF / tanpa order tunai).
 * Hanya membaca data; aturan foto/keterangan memakai logika yang sama dengan penghalang
 * administrasi di app teknisi (TeknisiService::fotoWajibKurang, UnitReportService::keteranganKurang).
 */
class KelengkapanHarianService
{
    public const BUTIR = ['absensi', 'dokumentasi', 'dana', 'setoran'];

    public function __construct(
        private readonly TeknisiService $teknisiService,
        private readonly UnitReportService $unitReports,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>> satu baris per teknisi aktif
     */
    public function untukTanggal(CarbonInterface $tanggal): Collection
    {
        $tgl = $tanggal->toDateString();
        $lewat = $tanggal->copy()->startOfDay()->lt(now()->startOfDay());

        $libur = TechnicianOffDay::query()->whereDate('tanggal', $tgl)->pluck('user_id')->flip();

        return User::role(RoleName::Teknisi->value)
            ->where('status', UserStatus::Aktif->value)
            ->orderBy('name')
            ->get()
            ->map(function (User $t) use ($tgl, $lewat, $libur): array {
                $off = $libur->has($t->id);

                $baris = [
                    'teknisi' => $t,
                    'libur' => $off,
                    'absensi' => $this->absensi($t, $tgl, $off),
                    'dokumentasi' => $this->dokumentasi($t, $tgl, $lewat),
                    'dana' => $this->dana($t, $tgl, $off),
                    'setoran' => $this->setoran($t, $tgl),
                ];

                $baris['lengkap'] = collect(self::BUTIR)->every(fn (string $b): bool => $baris[$b]['status'] !== 'kurang');

                return $baris;
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $baris
     * @return array{teknisi: int, lengkap: int, kurang: int, per_butir: array<string, int>}
     */
    public function ringkas(Collection $baris): array
    {
        return [
            'teknisi' => $baris->count(),
            'lengkap' => $baris->where('lengkap', true)->count(),
            'kurang' => $baris->where('lengkap', false)->count(),
            'per_butir' => collect(self::BUTIR)
                ->mapWithKeys(fn (string $b): array => [$b => $baris->filter(fn (array $r): bool => $r[$b]['status'] === 'kurang')->count()])
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function absensi(User $t, string $tgl, bool $off): array
    {
        $a = DailyAttendance::query()->where('user_id', $t->id)->whereDate('tanggal', $tgl)->first();

        if ($a === null || $a->jam_datang === null) {
            return $off
                ? ['status' => 'na', 'catatan' => 'Hari OFF', 'datang' => null, 'pulang' => null]
                : ['status' => 'kurang', 'catatan' => 'Belum absen datang', 'datang' => null, 'pulang' => null];
        }

        $pulang = $a->jam_pulang;

        return [
            'status' => $pulang !== null ? 'ok' : 'kurang',
            'catatan' => $pulang !== null ? ($a->status_datang?->value === 'telat' ? 'Datang terlambat' : null) : 'Belum absen pulang',
            'datang' => $a->jam_datang->format('H:i'),
            'pulang' => $pulang?->format('H:i'),
        ];
    }

    /** @return array<string, mixed> */
    private function dokumentasi(User $t, string $tgl, bool $lewat): array
    {
        $orders = Order::query()->untukTeknisi($t->id)
            ->whereDate('tanggal_jadwal', $tgl)
            ->whereNotIn('status', [OrderStatus::Batal->value, OrderStatus::Terkendala->value, OrderStatus::Baru->value])
            ->with(['customer', 'orderItems'])
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            return ['status' => 'na', 'total' => 0, 'lengkap' => 0, 'rincian' => []];
        }

        $rincian = [];
        $lengkap = 0;

        foreach ($orders as $o) {
            $nama = sprintf('#%d %s', $o->id, $o->customer?->nama ?? '-');

            if (! in_array($o->status, [OrderStatus::Selesai, OrderStatus::ButuhFollowup], true)) {
                // Hari ini masih boleh berjalan; hari lampau yang belum selesai = kurang.
                if ($lewat) {
                    $rincian[] = $nama.': belum diselesaikan';
                }

                continue;
            }

            $kurang = collect($this->teknisiService->fotoWajibKurang($o))
                ->merge($this->unitReports->keteranganKurang($o))
                ->pluck('label')->filter()->unique()->values();

            if ($kurang->isEmpty()) {
                $lengkap++;
            } else {
                $rincian[] = $nama.': kurang '.$kurang->implode(', ');
            }
        }

        return [
            'status' => $rincian === [] ? 'ok' : 'kurang',
            'total' => $orders->count(),
            'lengkap' => $lengkap,
            'rincian' => $rincian,
        ];
    }

    /** @return array<string, mixed> */
    private function dana(User $t, string $tgl, bool $off): array
    {
        $e = TeknisiExpense::query()->tercatatUntuk($t)->whereDate('tanggal_input', $tgl)->get();

        $pending = $e->where('status', 'pending')->count();
        $ditolak = $e->where('status', 'rejected')->count();
        $tanpaFoto = $e->filter(fn (TeknisiExpense $x): bool => blank($x->foto_path))->count();

        if ($e->isEmpty()) {
            return [
                'status' => $off ? 'na' : 'kurang',
                'jumlah' => 0, 'total' => 0, 'pending' => 0, 'ditolak' => 0, 'tanpa_foto' => 0,
                'catatan' => $off ? 'Hari OFF' : 'Belum ada catatan pengeluaran',
            ];
        }

        $catatan = collect([
            $pending > 0 ? "{$pending} menunggu persetujuan" : null,
            $ditolak > 0 ? "{$ditolak} ditolak" : null,
            $tanpaFoto > 0 ? "{$tanpaFoto} tanpa foto bukti" : null,
        ])->filter()->implode(' · ');

        return [
            // Ditolak/tanpa foto = perlu perhatian pimpinan, jadi dihitung kurang.
            'status' => ($ditolak > 0 || $tanpaFoto > 0) ? 'kurang' : 'ok',
            'jumlah' => $e->count(),
            'total' => (float) $e->where('status', '!=', 'rejected')->sum('nominal'),
            'pending' => $pending, 'ditolak' => $ditolak, 'tanpa_foto' => $tanpaFoto,
            'catatan' => $catatan !== '' ? $catatan : null,
        ];
    }

    /** @return array<string, mixed> */
    private function setoran(User $t, string $tgl): array
    {
        $orders = Order::query()->untukTeknisi($t->id)
            ->whereDate('tanggal_jadwal', $tgl)
            ->where('metode_dipilih', PaymentMethod::Cash->value)
            ->where('status', '!=', OrderStatus::Batal->value)
            ->with(['cashHandovers', 'orderItems', 'customer'])
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            return ['status' => 'na', 'wajib' => 0, 'tersetor' => 0, 'nominal_order' => 0, 'nominal_setor' => 0, 'rincian' => []];
        }

        $rincian = [];
        $tersetor = 0;
        $nominalOrder = 0.0;
        $nominalSetor = 0.0;

        foreach ($orders as $o) {
            $aktif = $o->cashHandovers->filter(fn ($h): bool => in_array($h->status, [CashHandoverStatus::Menunggu, CashHandoverStatus::Diterima], true));
            $total = (float) $o->total();
            $setor = (float) $aktif->sum('nominal');
            $nominalOrder += $total;
            $nominalSetor += $setor;
            $nama = sprintf('#%d %s', $o->id, $o->customer?->nama ?? '-');

            if ($aktif->isEmpty()) {
                $rincian[] = $nama.': belum ada laporan setoran'.($o->cashHandovers->contains(fn ($h): bool => $h->status === CashHandoverStatus::Ditolak) ? ' (setoran sebelumnya ditolak)' : '');

                continue;
            }

            $tersetor++;

            if (abs($total - $setor) >= 1) {
                $rincian[] = sprintf('%s: setoran Rp %s tidak sama dengan tagihan Rp %s', $nama, number_format($setor, 0, ',', '.'), number_format($total, 0, ',', '.'));
            }
        }

        return [
            'status' => $rincian === [] ? 'ok' : 'kurang',
            'wajib' => $orders->count(),
            'tersetor' => $tersetor,
            'nominal_order' => $nominalOrder,
            'nominal_setor' => $nominalSetor,
            'rincian' => $rincian,
        ];
    }
}
