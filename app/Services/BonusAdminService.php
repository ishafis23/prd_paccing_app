<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\BonusAdminSetting;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Bonus Admin/HRD/Finance (revisi 17 Nov 2026 poin 24): persen (default 1%)
 * dari omset jasa periode, baru diberikan bila omset jasa mencapai minimal
 * (default Rp 50.000.000). Omset jasa dari OmsetService — sumber yang sama
 * dengan dashboard dan Akuntan.
 */
class BonusAdminService
{
    public function __construct(private readonly OmsetService $omset) {}

    /**
     * @return array{
     *     aktif: bool, omset_jasa: float, minimal: float, persen: float, mode: string,
     *     memenuhi: bool, kurang: float, bonus_total: float, bonus_per_orang: float,
     *     penerima: array<int, array{id: int, nama: string, peran: string, bonus: float}>
     * }
     */
    public function hitung(CarbonInterface $dari, CarbonInterface $sampai, ?BonusAdminSetting $setting = null): array
    {
        $setting ??= BonusAdminSetting::saatIni();

        $omsetJasa = (float) $this->omset->total($this->omset->ringkasan($dari, $sampai))['jasa'];
        $minimal = (float) $setting->minimal_omset_jasa;
        $persen = (float) $setting->persen;

        $memenuhi = $setting->aktif && $omsetJasa >= $minimal;
        $bonusTotal = $memenuhi ? round($omsetJasa * $persen / 100, 2) : 0.0;

        $peran = $setting->peranPenerima();

        $users = User::query()
            ->role($peran)
            ->where('status', UserStatus::Aktif->value)
            ->orderBy('name')
            ->get();

        $perOrang = 0.0;
        if ($memenuhi && $users->isNotEmpty()) {
            $perOrang = $setting->mode === BonusAdminSetting::MODE_DIBAGI_RATA
                ? round($bonusTotal / $users->count(), 2)
                : $bonusTotal;
        }

        $penerima = $users->map(fn (User $u): array => [
            'id' => $u->id,
            'nama' => $u->name,
            'peran' => $u->getRoleNames()->intersect($peran)->map(fn (string $r): string => $this->labelPeran($r))->implode(', '),
            'bonus' => $perOrang,
        ])->values()->all();

        return [
            'aktif' => (bool) $setting->aktif,
            'omset_jasa' => $omsetJasa,
            'minimal' => $minimal,
            'persen' => $persen,
            'mode' => $setting->mode,
            'memenuhi' => $memenuhi,
            'kurang' => $setting->aktif ? max(0.0, $minimal - $omsetJasa) : 0.0,
            'bonus_total' => $bonusTotal,
            'bonus_per_orang' => $perOrang,
            'penerima' => $penerima,
        ];
    }

    public function labelPeran(string $peran): string
    {
        return match ($peran) {
            RoleName::Admin->value => 'Admin',
            RoleName::Hr->value => 'HRD',
            RoleName::Finance->value => 'Finance',
            RoleName::Owner->value => 'Owner',
            default => ucfirst($peran),
        };
    }
}
