<?php

namespace App\Services;

use App\Enums\IncentiveKategori;
use App\Enums\IncentiveStatusVerifikasi;
use App\Enums\IncentiveSumber;
use App\Enums\IncentiveTipe;
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
}
