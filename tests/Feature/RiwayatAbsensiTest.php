<?php

use App\Enums\DailyAttendanceStatus;
use App\Enums\RoleName;
use App\Models\DailyAttendance;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->teknisi = User::factory()->create();
    $this->teknisi->assignRole(RoleName::Teknisi->value);
});

it('halaman riwayat absensi bisa diakses teknisi & menampilkan jam datang/pulang', function () {
    DailyAttendance::create([
        'user_id' => $this->teknisi->id,
        'tanggal' => '2026-09-19',
        'jam_datang' => Carbon::parse('2026-09-19 07:30:00'),
        'jam_pulang' => Carbon::parse('2026-09-19 17:15:00'),
        'status_datang' => DailyAttendanceStatus::Normal->value,
    ]);

    $this->actingAs($this->teknisi)->get(route('teknisi.riwayat-absensi'))
        ->assertSuccessful()
        ->assertSee('07:30')
        ->assertSee('17:15')
        ->assertSee('Normal');
});

it('baris terlambat ditandai merah & label "Terlambat"', function () {
    DailyAttendance::create([
        'user_id' => $this->teknisi->id,
        'tanggal' => '2026-09-19',
        'jam_datang' => Carbon::parse('2026-09-19 09:00:00'),
        'status_datang' => DailyAttendanceStatus::Telat->value,
    ]);

    $this->actingAs($this->teknisi)->get(route('teknisi.riwayat-absensi'))
        ->assertSuccessful()
        ->assertSee('Terlambat')
        ->assertSee('ring-rose-200', false)
        ->assertSee('text-rose-600', false);
});

it('hanya menampilkan riwayat milik teknisi yang login, bukan teknisi lain', function () {
    $teknisiLain = User::factory()->create();
    $teknisiLain->assignRole(RoleName::Teknisi->value);

    DailyAttendance::create([
        'user_id' => $teknisiLain->id,
        'tanggal' => '2026-09-19',
        'jam_datang' => Carbon::parse('2026-09-19 07:00:00'),
        'status_datang' => DailyAttendanceStatus::Bonus->value,
    ]);

    $this->actingAs($this->teknisi)->get(route('teknisi.riwayat-absensi'))
        ->assertSuccessful()
        ->assertSee('Belum ada riwayat absensi');
});

it('tombol Riwayat tampil di halaman Absensi', function () {
    $this->actingAs($this->teknisi)->get(route('teknisi.absensi'))
        ->assertSuccessful()
        ->assertSee('Riwayat')
        ->assertSee(route('teknisi.riwayat-absensi'), false);
});
