<?php

namespace App\Services;

use App\Enums\IncentiveKategori;
use App\Enums\IncentiveStatusVerifikasi;
use App\Enums\IncentiveSumber;
use App\Enums\IncentiveTipe;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Order;
use App\Models\TechnicianIncentive;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Ledger generik bonus/denda teknisi (dev-plan/15, §4): 1 baris per
 * teknisi/tanggal/kategori. Dipakai oleh AttendanceService (Games 1/4 +
 * denda telat otomatis) dan Filament Rekap Insentif (entri manual + B55).
 */
class TechnicianIncentiveService
{
    use RestrictsByRole;

    private const PENGELOLA_ROLES = [RoleName::Admin, RoleName::Hr, RoleName::Owner];

    /**
     * Catat/perbarui entri ledger (idempoten per user+tanggal+kategori —
     * dipanggil ulang mis. saat absen datang di-generate ulang harusnya
     * tidak menggandakan baris).
     */
    public function catat(
        User $teknisi,
        Carbon $tanggal,
        IncentiveKategori $kategori,
        IncentiveTipe $tipe,
        float $nominal,
        ?string $fotoBukti = null,
        IncentiveSumber $sumber = IncentiveSumber::Otomatis,
        ?string $referensi = null,
        ?User $dicatatOleh = null,
        ?string $catatan = null,
    ): TechnicianIncentive {
        return TechnicianIncentive::updateOrCreate(
            [
                'user_id' => $teknisi->id,
                'tanggal' => $tanggal->toDateString(),
                'kategori' => $kategori->value,
            ],
            [
                'tipe' => $tipe->value,
                'nominal' => $nominal,
                'foto_bukti' => $fotoBukti,
                'sumber' => $sumber->value,
                'referensi' => $referensi,
                'dicatat_oleh' => $dicatatOleh?->id,
                'catatan' => $catatan,
                // B55: entri berfoto menunggu verifikasi; tanpa foto langsung disetujui.
                'status_verifikasi' => $fotoBukti !== null
                    ? IncentiveStatusVerifikasi::Menunggu->value
                    : IncentiveStatusVerifikasi::Disetujui->value,
            ]
        );
    }

    /**
     * Entri manual Admin/HR (dev-plan/15, B53) — dipakai sementara utk
     * Games 5/6 sampai otomatisasi penuh (menunggu B50/definisi omset).
     */
    public function catatManual(
        User $teknisi,
        Carbon $tanggal,
        IncentiveKategori $kategori,
        IncentiveTipe $tipe,
        float $nominal,
        User $dicatatOleh,
        ?string $catatan = null,
    ): TechnicianIncentive {
        $this->assertRole($dicatatOleh, self::PENGELOLA_ROLES);

        return $this->catat($teknisi, $tanggal, $kategori, $tipe, $nominal, null, IncentiveSumber::Manual, null, $dicatatOleh, $catatan);
    }

    public function setujui(TechnicianIncentive $entry, User $by): TechnicianIncentive
    {
        $this->assertRole($by, self::PENGELOLA_ROLES);

        $entry->status_verifikasi = IncentiveStatusVerifikasi::Disetujui;
        $entry->diverifikasi_pada = now();
        $entry->diverifikasi_oleh = $by->id;
        $entry->save();

        return $entry->fresh();
    }

    public function tolak(TechnicianIncentive $entry, User $by, ?string $alasan = null): TechnicianIncentive
    {
        $this->assertRole($by, self::PENGELOLA_ROLES);

        $entry->status_verifikasi = IncentiveStatusVerifikasi::Ditolak;
        $entry->diverifikasi_pada = now();
        $entry->diverifikasi_oleh = $by->id;

        if ($alasan !== null) {
            $entry->catatan = $alasan;
        }

        $entry->save();

        return $entry->fresh();
    }

    /**
     * Jumlah "titik" (order selesai/ditutup) teknisi pada tanggal tsb
     * (dev-plan/15, B52). Order klaim dikecualikan (B50).
     */
    public function hitungTitikHarian(User $teknisi, Carbon $tanggal): int
    {
        return Order::query()
            ->untukTeknisi($teknisi->id)
            ->whereNotNull('ditutup_pada')
            ->whereDate('ditutup_pada', $tanggal->toDateString())
            ->where('is_klaim', false)
            ->count();
    }

    /**
     * "berdua"/"sendiri" (dev-plan/15, B51): dihitung dari jumlah teknisi
     * berbeda yang sama-sama mengerjakan seluruh titik hari itu (klaim
     * dikecualikan, B50). Null bila tidak ada titik atau campur (butuh
     * review manual Admin/HR).
     */
    public function tentukanModeJalan(User $teknisi, Carbon $tanggal): ?string
    {
        $orders = Order::query()
            ->untukTeknisi($teknisi->id)
            ->whereNotNull('ditutup_pada')
            ->whereDate('ditutup_pada', $tanggal->toDateString())
            ->where('is_klaim', false)
            ->with('orderTechnicians')
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $jumlahAnggota = $orders
            ->map(fn (Order $order) => $order->orderTechnicians
                ->pluck('teknisi_id')
                ->push($order->teknisi_id)
                ->filter()
                ->unique()
                ->count())
            ->unique();

        if ($jumlahAnggota->count() !== 1) {
            return null;
        }

        return match ($jumlahAnggota->first()) {
            1 => 'sendiri',
            2 => 'berdua',
            default => null,
        };
    }

    /**
     * Order tim (PIC atau anggota) yang dihitung untuk Games 5 & 6 pada satu tanggal.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Order>
     */
    public function ordersGames56(User $teknisi, Carbon $tanggal): \Illuminate\Database\Eloquent\Collection
    {
        return Order::query()
            ->untukTeknisi($teknisi->id)
            ->where('status', OrderStatus::Selesai->value)
            ->where('is_klaim', false)
            ->whereRaw(OmsetService::tanggalPendapatanSql().' = ?', [$tanggal->toDateString()])
            ->with(['customer', 'orderItems', 'latestPayment', 'orderTechnicians'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Ringkasan Games 5 (omset jasa tim) & Games 6 (unit) satu teknisi pada
     * satu tanggal. Tanggal = tanggal order DIJADWALKAN/dikerjakan (sama dengan
     * omset di admin & portal teknisi), bukan tanggal laporan/pembayaran.
     * Order klaim tidak dihitung. `mode` null = tidak ada order / tim campur.
     *
     * @return array{omset: float, unit: int, mode: ?string, minimal_omset: float, minimal_unit: ?int, dapat_games5: bool, dapat_games6: bool, nominal_games5: float, nominal_games6: float}
     */
    public function ringkasanGames56(User $teknisi, Carbon $tanggal): array
    {
        $settings = app(AttendanceSettingService::class)->data();

        $orders = $this->ordersGames56($teknisi, $tanggal);

        $omset = (float) $orders->sum(fn (Order $o): float => $o->omsetJasa());
        $unit = (int) $orders->sum(fn (Order $o): int => $o->jumlahUnit());

        $anggota = $orders
            ->map(fn (Order $o) => $o->orderTechnicians->pluck('teknisi_id')->push($o->teknisi_id)->filter()->unique()->count())
            ->unique();

        $mode = $anggota->count() === 1 ? match ($anggota->first()) {
            1 => 'sendiri',
            2 => 'berdua',
            default => null,
        } : null;

        $minimalUnit = match ($mode) {
            'berdua' => (int) $settings->unit_games6_berdua,
            'sendiri' => (int) $settings->unit_games6_sendiri,
            default => null,
        };

        $dapat5 = $mode !== null && $omset >= (float) $settings->omset_games5_minimal;
        $dapat6 = $mode !== null && $minimalUnit !== null && $unit >= $minimalUnit;

        return [
            'omset' => $omset,
            'unit' => $unit,
            'mode' => $mode,
            'minimal_omset' => (float) $settings->omset_games5_minimal,
            'minimal_unit' => $minimalUnit,
            'dapat_games5' => $dapat5,
            'dapat_games6' => $dapat6,
            'nominal_games5' => $dapat5 ? (float) ($mode === 'berdua' ? $settings->nominal_games5_berdua : $settings->nominal_games5_sendiri) : 0.0,
            'nominal_games6' => $dapat6 ? (float) $settings->nominal_games6 : 0.0,
        ];
    }

    /**
     * Sinkronkan ledger Games 5 & 6 teknisi pada satu tanggal: catat bila
     * tercapai, hapus entri otomatis bila tidak lagi tercapai (mis. order
     * dibatalkan / omset berubah). Entri manual Admin/HR tidak disentuh.
     */
    public function hitungGames56(User $teknisi, Carbon $tanggal): void
    {
        $r = $this->ringkasanGames56($teknisi, $tanggal);

        foreach ([
            [IncentiveKategori::Games5OmsetTim, $r['dapat_games5'], $r['nominal_games5'], sprintf('Omset jasa Rp %s (%s)', number_format($r['omset'], 0, ',', '.'), $r['mode'])],
            [IncentiveKategori::Games6Unit, $r['dapat_games6'], $r['nominal_games6'], sprintf('%d unit (%s)', $r['unit'], $r['mode'])],
        ] as [$kategori, $tercapai, $nominal, $catatan]) {
            $ada = TechnicianIncentive::query()
                ->where('user_id', $teknisi->id)
                ->whereDate('tanggal', $tanggal->toDateString())
                ->where('kategori', $kategori->value)
                ->first();

            if ($ada !== null && $ada->sumber !== IncentiveSumber::Otomatis) {
                continue;
            }

            if ($tercapai) {
                $this->catat($teknisi, $tanggal, $kategori, IncentiveTipe::Bonus, $nominal, null, IncentiveSumber::Otomatis, null, null, $catatan);
            } elseif ($ada !== null) {
                $ada->delete();
            }
        }
    }

    /** Hitung Games 5/6 untuk seluruh anggota tim sebuah order pada tanggal jadwalnya. */
    public function hitungGames56UntukOrder(Order $order): void
    {
        if ($order->tanggal_jadwal === null) {
            return;
        }

        $ids = $order->orderTechnicians()->pluck('teknisi_id')->push($order->teknisi_id)->filter()->unique();

        User::query()->whereIn('id', $ids)->get()
            ->each(fn (User $u) => $this->hitungGames56($u, Carbon::parse($order->tanggal_jadwal)));
    }
}
