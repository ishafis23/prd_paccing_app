<?php

namespace App\Services;

use App\Enums\AttendanceCodeStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\AttendanceCode;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Kode/QR absensi kantor (dev-plan/15, B39-B41). Hanya 1 kode `aktif`
 * berlaku di satu waktu — generate baru otomatis nonaktifkan yang lama.
 */
class AttendanceCodeService
{
    use RestrictsByRole;

    private const PENGELOLA_ROLES = [RoleName::Admin, RoleName::Hr, RoleName::Owner];

    /**
     * Generate kode baru & nonaktifkan kode aktif sebelumnya (kalau ada).
     */
    public function buatBaru(User $by, ?string $lokasi = null, ?Carbon $berlakuSampai = null): AttendanceCode
    {
        $this->assertRole($by, self::PENGELOLA_ROLES);

        AttendanceCode::query()
            ->where('status', AttendanceCodeStatus::Aktif)
            ->update(['status' => AttendanceCodeStatus::Nonaktif->value]);

        return AttendanceCode::create([
            'kode' => Str::random(40),
            'status' => AttendanceCodeStatus::Aktif,
            'lokasi' => $lokasi,
            'berlaku_sampai' => $berlakuSampai,
            'dibuat_oleh' => $by->id,
        ]);
    }

    public function aktifkan(AttendanceCode $kode, User $by): AttendanceCode
    {
        $this->assertRole($by, self::PENGELOLA_ROLES);

        AttendanceCode::query()
            ->where('id', '!=', $kode->id)
            ->where('status', AttendanceCodeStatus::Aktif)
            ->update(['status' => AttendanceCodeStatus::Nonaktif->value]);

        // Update lewat query, bukan mutate+save: $kode di memory bisa saja
        // sudah stale (mis. dinonaktifkan raw query oleh buatBaru() lain)
        // sehingga Eloquent menganggap tidak ada perubahan & skip UPDATE-nya.
        AttendanceCode::query()->whereKey($kode->id)->update(['status' => AttendanceCodeStatus::Aktif->value]);

        return $kode->fresh();
    }

    public function nonaktifkan(AttendanceCode $kode, User $by): AttendanceCode
    {
        $this->assertRole($by, self::PENGELOLA_ROLES);

        AttendanceCode::query()->whereKey($kode->id)->update(['status' => AttendanceCodeStatus::Nonaktif->value]);

        return $kode->fresh();
    }

    /**
     * Kode aktif & belum kedaluwarsa saat ini; null bila tidak valid.
     */
    public function kodeAktifValid(string $kode): ?AttendanceCode
    {
        $record = AttendanceCode::query()->where('kode', $kode)->first();

        if (! $record || $record->status !== AttendanceCodeStatus::Aktif) {
            return null;
        }

        if ($record->berlaku_sampai !== null && $record->berlaku_sampai->isPast()) {
            return null;
        }

        return $record;
    }

    /**
     * @throws BusinessRuleException bila kode tidak valid/aktif/expired.
     */
    public function validasiAtauGagal(string $kode): AttendanceCode
    {
        $record = $this->kodeAktifValid($kode);

        if (! $record) {
            throw new BusinessRuleException('Kode absensi tidak berlaku. Hubungi admin.');
        }

        return $record;
    }
}
