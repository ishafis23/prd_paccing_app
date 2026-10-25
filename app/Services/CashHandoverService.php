<?php

namespace App\Services;

use App\Enums\CashHandoverStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\CashHandover;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Setoran uang tunai teknisi (dev-plan/29 C2): teknisi melaporkan nominal,
 * penerima, dan foto bukti; Owner/Admin/Finance memverifikasi. Verifikasi
 * tidak memblokir teknisi — cukup laporannya ada.
 */
class CashHandoverService
{
    use RestrictsByRole;

    /**
     * Order tunai milik teknisi yang belum punya laporan setoran aktif
     * (menunggu/diterima). Handover `ditolak` mengembalikan kewajiban.
     *
     * @return Collection<int, Order>
     */
    public function kewajiban(User $teknisi): Collection
    {
        return Order::query()
            ->untukTeknisi($teknisi->id)
            ->where('metode_dipilih', PaymentMethod::Cash->value)
            ->where('status', '!=', OrderStatus::Batal->value)
            ->whereDoesntHave('cashHandovers', fn ($q) => $q->whereIn('status', [
                CashHandoverStatus::Menunggu->value,
                CashHandoverStatus::Diterima->value,
            ]))
            ->with(['customer', 'orderItems'])
            ->orderBy('tanggal_jadwal')
            ->orderBy('id')
            ->get();
    }

    /**
     * Kewajiban yang tenggangnya (sehari) sudah lewat — metode tunai ditandai
     * SEBELUM hari ini. Inilah yang mengunci portal teknisi.
     *
     * @return Collection<int, Order>
     */
    public function telat(User $teknisi): Collection
    {
        $hariIni = now()->startOfDay();

        return $this->kewajiban($teknisi)->filter(function (Order $order) use ($hariIni): bool {
            $acuan = $order->metode_dipilih_pada ?? $order->tanggal_jadwal;

            return $acuan !== null && $acuan->copy()->startOfDay()->lt($hariIni);
        })->values();
    }

    /**
     * @param  array{nominal?: mixed, diserahkan_ke?: ?string, foto_path?: ?string, tanggal_setor?: mixed, catatan?: ?string}  $data
     */
    public function catat(Order $order, User $teknisi, array $data): CashHandover
    {
        if (! $order->diassignkanKe($teknisi)) {
            throw new AuthorizationException('Order ini bukan tugas teknisi Anda.');
        }

        if (! $order->metodeTunai()) {
            throw new BusinessRuleException('Order ini tidak bertanda pembayaran tunai.');
        }

        $nominal = (float) ($data['nominal'] ?? 0);
        if ($nominal <= 0) {
            throw new BusinessRuleException('Nominal setoran harus lebih dari 0.');
        }

        $diserahkanKe = trim((string) ($data['diserahkan_ke'] ?? ''));
        if (mb_strlen($diserahkanKe) < 3) {
            throw new BusinessRuleException('Isi nama penerima setoran (minimal 3 karakter).');
        }

        $foto = (string) ($data['foto_path'] ?? '');
        if ($foto === '' || ! Storage::disk('public')->exists($foto)) {
            throw new BusinessRuleException('Foto bukti setoran wajib diunggah.');
        }

        $tanggal = $data['tanggal_setor'] ?? null;
        if (blank($tanggal)) {
            throw new BusinessRuleException('Tanggal setoran wajib diisi.');
        }
        $tanggal = \Illuminate\Support\Carbon::parse($tanggal)->startOfDay();
        if ($tanggal->gt(now()->endOfDay())) {
            throw new BusinessRuleException('Tanggal setoran tidak boleh di masa depan.');
        }

        $sudahAda = $order->cashHandovers()
            ->whereIn('status', [CashHandoverStatus::Menunggu->value, CashHandoverStatus::Diterima->value])
            ->exists();
        if ($sudahAda) {
            throw new BusinessRuleException('Setoran order ini sudah dilaporkan.');
        }

        return CashHandover::create([
            'order_id' => $order->id,
            'teknisi_id' => $teknisi->id,
            'tanggal_setor' => $tanggal->toDateString(),
            'nominal' => $nominal,
            'diserahkan_ke' => $diserahkanKe,
            'foto_path' => $foto,
            'catatan' => filled($data['catatan'] ?? null) ? trim((string) $data['catatan']) : null,
            'status' => CashHandoverStatus::Menunggu,
        ]);
    }

    public function verifikasi(CashHandover $handover, User $admin, bool $diterima, ?string $catatan = null): CashHandover
    {
        $this->assertRole($admin, [RoleName::Owner, RoleName::Admin, RoleName::Finance]);

        if ($handover->status !== CashHandoverStatus::Menunggu) {
            throw new BusinessRuleException('Setoran ini sudah diverifikasi.');
        }

        $handover->update([
            'status' => $diterima ? CashHandoverStatus::Diterima : CashHandoverStatus::Ditolak,
            'diterima_oleh' => $admin->id,
            'diterima_pada' => now(),
            'catatan_verifikasi' => filled($catatan) ? trim($catatan) : null,
        ]);

        return $handover->fresh();
    }

    /**
     * @return array{kewajiban: int, telat: int, total_telat: float}
     */
    public function ringkasanTeknisi(User $teknisi): array
    {
        $telat = $this->telat($teknisi);

        return [
            'kewajiban' => $this->kewajiban($teknisi)->count(),
            'telat' => $telat->count(),
            'total_telat' => (float) $telat->sum(fn (Order $o): float => $o->total()),
        ];
    }
}
