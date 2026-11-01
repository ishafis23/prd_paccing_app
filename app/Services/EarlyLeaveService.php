<?php

namespace App\Services;

use App\Enums\EarlyLeaveStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\TechnicianEarlyLeave;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Pengajuan & verifikasi pulang cepat teknisi (dev-plan/hrd/02). Teknisi
 * mengajukan; HRD/Admin/Owner menyetujui. Yang disetujui dianggap setengah
 * hari di penggajian.
 */
class EarlyLeaveService
{
    use RestrictsByRole;

    private const PENGELOLA_ROLES = [RoleName::Owner, RoleName::Hr, RoleName::Admin];

    public function ajukan(User $teknisi, string $tanggal, string $jamPulang, ?string $alasan = null): TechnicianEarlyLeave
    {
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        $tgl = CarbonImmutable::parse($tanggal)->startOfDay();

        if ($tgl->greaterThan(CarbonImmutable::now()->endOfDay())) {
            throw new BusinessRuleException('Tanggal pulang cepat tidak boleh di masa depan.');
        }

        return TechnicianEarlyLeave::updateOrCreate(
            ['user_id' => $teknisi->id, 'tanggal' => $tgl->toDateString()],
            [
                'jam_pulang' => $this->normalisasiJam($jamPulang),
                'alasan' => filled($alasan) ? trim((string) $alasan) : null,
                'status' => EarlyLeaveStatus::Menunggu,
                'catatan_hrd' => null,
                'diverifikasi_oleh' => null,
                'diverifikasi_pada' => null,
            ],
        );
    }

    /**
     * Admin/HRD/Owner menginput langsung (tanpa pengajuan teknisi): langsung
     * disetujui, dengan nominal potongan yang bisa ditentukan.
     */
    public function inputOlehAdmin(User $actor, int $teknisiId, string $tanggal, string $jamPulang, ?string $alasan, ?float $nominal): TechnicianEarlyLeave
    {
        $this->assertRole($actor, self::PENGELOLA_ROLES);

        return TechnicianEarlyLeave::updateOrCreate(
            ['user_id' => $teknisiId, 'tanggal' => CarbonImmutable::parse($tanggal)->toDateString()],
            [
                'jam_pulang' => $this->normalisasiJam($jamPulang),
                'alasan' => filled($alasan) ? trim((string) $alasan) : null,
                'nominal_potongan' => $nominal,
                'status' => EarlyLeaveStatus::Disetujui,
                'diverifikasi_oleh' => $actor->id,
                'diverifikasi_pada' => now(),
            ],
        );
    }

    public function ubahNominal(TechnicianEarlyLeave $entry, User $actor, ?float $nominal): TechnicianEarlyLeave
    {
        $this->assertRole($actor, self::PENGELOLA_ROLES);

        $entry->nominal_potongan = $nominal;
        $entry->save();

        return $entry->fresh();
    }

    public function setujui(TechnicianEarlyLeave $entry, User $actor, ?string $catatan = null): TechnicianEarlyLeave
    {
        $this->assertRole($actor, self::PENGELOLA_ROLES);

        $entry->status = EarlyLeaveStatus::Disetujui;
        $entry->catatan_hrd = filled($catatan) ? trim((string) $catatan) : $entry->catatan_hrd;
        $entry->diverifikasi_oleh = $actor->id;
        $entry->diverifikasi_pada = now();
        $entry->save();

        return $entry->fresh();
    }

    public function tolak(TechnicianEarlyLeave $entry, User $actor, ?string $catatan = null): TechnicianEarlyLeave
    {
        $this->assertRole($actor, self::PENGELOLA_ROLES);

        $entry->status = EarlyLeaveStatus::Ditolak;
        $entry->catatan_hrd = filled($catatan) ? trim((string) $catatan) : $entry->catatan_hrd;
        $entry->diverifikasi_oleh = $actor->id;
        $entry->diverifikasi_pada = now();
        $entry->save();

        return $entry->fresh();
    }

    private function normalisasiJam(string $jamPulang): string
    {
        try {
            return CarbonImmutable::parse($jamPulang)->format('H:i:s');
        } catch (\Throwable) {
            throw new BusinessRuleException('Jam pulang tidak valid.');
        }
    }
}
