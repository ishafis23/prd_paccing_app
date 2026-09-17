<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\AttendanceSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan absensi & skema "Games" (dev-plan/15, B42): baris tunggal
 * `attendance_settings` + cache pendek — admin-editable tanpa deploy.
 */
class AttendanceSettingService
{
    use RestrictsByRole;

    private const PENGELOLA_ROLES = [RoleName::Admin, RoleName::Hr, RoleName::Owner];

    private const CACHE_KEY = 'pengaturan_absensi.data';

    /**
     * Ambil pengaturan (selalu ada: bila baris belum ada, dibuat dengan
     * nilai default kolom). Hasil di-cache 5 menit.
     */
    public function data(): AttendanceSetting
    {
        return Cache::remember(self::CACHE_KEY, 300, function (): AttendanceSetting {
            // fresh() wajib: create([]) tidak membawa balik nilai default kolom DB
            // ke instance PHP-nya (INSERT tanpa kolom tsb, defaultnya cuma di sisi DB).
            return AttendanceSetting::query()->first() ?? AttendanceSetting::create([])->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data  subset kolom `attendance_settings`
     */
    public function perbarui(array $data, User $by): AttendanceSetting
    {
        $this->assertRole($by, self::PENGELOLA_ROLES);

        $setting = $this->data();
        $setting->fill($data);
        $setting->save();

        $this->lupakanCache();

        return $setting->fresh();
    }

    public static function lupakanCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
