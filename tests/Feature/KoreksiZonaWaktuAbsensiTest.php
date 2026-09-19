<?php

use App\Enums\DailyAttendanceStatus;
use App\Enums\IncentiveKategori;
use App\Enums\IncentiveStatusVerifikasi;
use App\Enums\IncentiveTipe;
use App\Enums\RoleName;
use App\Models\DailyAttendance;
use App\Models\TechnicianIncentive;
use App\Models\User;
use App\Services\AttendanceSettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    AttendanceSettingService::lupakanCache();

    $mkUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };

    $this->admin = $mkUser(RoleName::Admin->value);
    $this->teknisi = $mkUser(RoleName::Teknisi->value);
});

/**
 * Simulasi persis insiden 19 Sep 2026: jam_datang tersimpan sbg jam UTC
 * (dianggap "01:00") padahal aslinya 09:00 WITA (Asia/Makassar = UTC+8).
 * Kode LAMA (saat config masih UTC) salah membandingkan "01:00" itu ke
 * ambang jam_games1_batas (07:35) — 01:00 <= 07:35, jadi keliru dicatat
 * status "bonus". Yang benar (09:00 WITA, lewat jam_normal_selesai
 * 08:05, tanpa toleransi lembur) harusnya "telat".
 */
it('dry run (tanpa --apply): melaporkan tapi TIDAK mengubah data', function () {
    $absen = DailyAttendance::create([
        'user_id' => $this->teknisi->id,
        'tanggal' => '2026-09-19',
        'jam_datang' => Carbon::parse('2026-09-19 01:00:00'),
        'status_datang' => DailyAttendanceStatus::Bonus->value,
    ]);

    $bonus = TechnicianIncentive::create([
        'user_id' => $this->teknisi->id,
        'tanggal' => '2026-09-19',
        'kategori' => IncentiveKategori::Games1Hadir->value,
        'tipe' => IncentiveTipe::Bonus->value,
        'nominal' => 7500,
        'status_verifikasi' => IncentiveStatusVerifikasi::Disetujui->value,
    ]);

    $this->artisan('absensi:koreksi-zona-waktu')->assertSuccessful();

    expect($absen->fresh()->jam_datang->format('H:i'))->toBe('01:00')
        ->and($absen->fresh()->status_datang)->toBe(DailyAttendanceStatus::Bonus)
        ->and($bonus->fresh()->status_verifikasi)->toBe(IncentiveStatusVerifikasi::Disetujui);
});

it('--apply: menggeser jam +8, hitung ulang status_datang, & batalkan bonus Games 1 yang salah', function () {
    $absen = DailyAttendance::create([
        'user_id' => $this->teknisi->id,
        'tanggal' => '2026-09-19',
        'jam_datang' => Carbon::parse('2026-09-19 01:00:00'),
        'status_datang' => DailyAttendanceStatus::Bonus->value,
    ]);

    $bonus = TechnicianIncentive::create([
        'user_id' => $this->teknisi->id,
        'tanggal' => '2026-09-19',
        'kategori' => IncentiveKategori::Games1Hadir->value,
        'tipe' => IncentiveTipe::Bonus->value,
        'nominal' => 7500,
        'status_verifikasi' => IncentiveStatusVerifikasi::Disetujui->value,
    ]);

    $this->artisan('absensi:koreksi-zona-waktu', ['--apply' => true])->assertSuccessful();

    expect($absen->fresh()->jam_datang->format('H:i'))->toBe('09:00')
        ->and($absen->fresh()->status_datang)->toBe(DailyAttendanceStatus::Telat)
        ->and($bonus->fresh()->status_verifikasi)->toBe(IncentiveStatusVerifikasi::Ditolak)
        ->and($bonus->fresh()->diverifikasi_oleh)->not->toBeNull();
});

it('--apply: membatalkan denda telat yang ternyata salah (sekarang tidak telat)', function () {
    // "05:00" UTC-label = 13:00 WITA asli -> lewat toleransi, dulu keliru
    // dianggap "05:00" (sebelum jam_normal_selesai) makanya lolos sbg
    // Normal, TAPI utk kasus ini kita simulasikan sisi sebaliknya: jam
    // tersimpan "23:30" (dianggap lewat toleransi -> Telat keliru),
    // padahal 23:30 + 8 jam = 07:30 keesokan harinya WITA (tepat waktu).
    $absen = DailyAttendance::create([
        'user_id' => $this->teknisi->id,
        'tanggal' => '2026-09-18',
        'jam_datang' => Carbon::parse('2026-09-18 23:30:00'),
        'status_datang' => DailyAttendanceStatus::Telat->value,
    ]);

    $denda = TechnicianIncentive::create([
        'user_id' => $this->teknisi->id,
        'tanggal' => '2026-09-18',
        'kategori' => IncentiveKategori::DendaTelat->value,
        'tipe' => IncentiveTipe::Denda->value,
        'nominal' => 7500,
        'status_verifikasi' => IncentiveStatusVerifikasi::Disetujui->value,
    ]);

    $this->artisan('absensi:koreksi-zona-waktu', ['--apply' => true])->assertSuccessful();

    expect($absen->fresh()->jam_datang->format('Y-m-d H:i'))->toBe('2026-09-19 07:30')
        ->and($absen->fresh()->status_datang)->toBe(DailyAttendanceStatus::Bonus)
        ->and($denda->fresh()->status_verifikasi)->toBe(IncentiveStatusVerifikasi::Ditolak);
});

it('tidak ada baris daily_attendances -> selesai tanpa error', function () {
    $this->artisan('absensi:koreksi-zona-waktu', ['--apply' => true])->assertSuccessful();
});
