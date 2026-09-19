<?php

use App\Enums\AttendanceMode;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Pages\PengaturanAbsensi;
use App\Filament\Resources\AttendanceLocationResource;
use App\Filament\Resources\AttendanceLocationResource\Pages\ListAttendanceLocations;
use App\Livewire\Teknisi\AbsensiScan;
use App\Models\AttendanceLocation;
use App\Models\DailyAttendance;
use App\Models\User;
use App\Services\AttendanceLocationService;
use App\Services\AttendanceService;
use App\Services\AttendanceSettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    AttendanceSettingService::lupakanCache();

    $this->mkUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };

    $this->admin = ($this->mkUser)(RoleName::Admin->value);
    $this->teknisi = ($this->mkUser)(RoleName::Teknisi->value);

    // Kantor di titik acuan; radius default 50m.
    $this->kantor = AttendanceLocation::create([
        'nama' => 'Kantor Pusat',
        'latitude' => -6.2000000,
        'longitude' => 106.8166000,
        'radius_meter' => 50,
        'aktif' => true,
        'dibuat_oleh' => $this->admin->id,
    ]);

    app(AttendanceSettingService::class)->perbarui(['mode_absensi' => AttendanceMode::Lokasi->value], $this->admin);
});

// --- AttendanceLocationService -------------------------------------------

it('hitungJarakMeter menghitung jarak akurat (Haversine)', function () {
    $service = app(AttendanceLocationService::class);

    // Beda 0.0005 derajat lintang (arah utara-selatan murni) ~= 55.6 meter.
    $jarak = $service->hitungJarakMeter(-6.2000000, 106.8166000, -6.2005000, 106.8166000);

    expect($jarak)->toBeGreaterThan(54.0)->toBeLessThan(57.0);
});

it('evaluasiLokasi: masuk=true kalau dalam radius lokasi aktif terdekat', function () {
    $service = app(AttendanceLocationService::class);

    $hasil = $service->evaluasiLokasi(-6.2000100, 106.8166000); // ~1m dari kantor

    expect($hasil['masuk'])->toBeTrue()
        ->and($hasil['lokasi']->id)->toBe($this->kantor->id);
});

it('evaluasiLokasi: masuk=false + sebut lokasi & jarak terdekat kalau di luar radius semua lokasi', function () {
    $service = app(AttendanceLocationService::class);

    $hasil = $service->evaluasiLokasi(-6.3000000, 106.8166000); // jauh dari kantor

    expect($hasil['masuk'])->toBeFalse()
        ->and($hasil['lokasi']->id)->toBe($this->kantor->id)
        ->and($hasil['jarak_meter'])->toBeGreaterThan(50);
});

it('evaluasiLokasi: lokasi null kalau tidak ada lokasi aktif sama sekali', function () {
    AttendanceLocation::query()->delete();

    $hasil = app(AttendanceLocationService::class)->evaluasiLokasi(-6.2000000, 106.8166000);

    expect($hasil['masuk'])->toBeFalse()->and($hasil['lokasi'])->toBeNull();
});

it('evaluasiLokasi mengabaikan lokasi nonaktif', function () {
    $this->kantor->update(['aktif' => false]);

    $hasil = app(AttendanceLocationService::class)->evaluasiLokasi(-6.2000000, 106.8166000);

    expect($hasil['lokasi'])->toBeNull();
});

it('tambah/perbarui/toggleAktif/hapus hanya boleh role pengelola', function () {
    $teknisi = $this->teknisi;
    $service = app(AttendanceLocationService::class);

    expect(fn () => $service->tambah(['nama' => 'X', 'latitude' => 0, 'longitude' => 0], $teknisi))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $service->toggleAktif($this->kantor, $teknisi))
        ->toThrow(AuthorizationException::class);
});

it('toggleAktif membalik nilai aktif', function () {
    app(AttendanceLocationService::class)->toggleAktif($this->kantor, $this->admin);

    expect($this->kantor->fresh()->aktif)->toBeFalse();
});

// --- AttendanceService::catatDatang (mode lokasi) -------------------------

it('catatDatang mode lokasi berhasil kalau dalam radius, mencatat attendance_location_id', function () {
    $absen = app(AttendanceService::class)->catatDatang(
        $this->teknisi,
        null,
        UploadedFile::fake()->image('datang.jpg'),
        -6.2000100,
        106.8166000,
    );

    expect($absen->attendance_location_id)->toBe($this->kantor->id)
        ->and($absen->attendance_code_id)->toBeNull()
        ->and($absen->jam_datang)->not->toBeNull();
});

it('catatDatang mode lokasi ditolak kalau di luar radius, pesan sebut jarak & nama lokasi', function () {
    expect(fn () => app(AttendanceService::class)->catatDatang(
        $this->teknisi,
        null,
        UploadedFile::fake()->image('datang.jpg'),
        -6.3000000,
        106.8166000,
    ))->toThrow(BusinessRuleException::class, 'Kantor Pusat');

    expect(DailyAttendance::query()->where('user_id', $this->teknisi->id)->exists())->toBeFalse();
});

it('catatDatang mode lokasi ditolak kalau koordinat tidak dikirim', function () {
    expect(fn () => app(AttendanceService::class)->catatDatang(
        $this->teknisi,
        null,
        UploadedFile::fake()->image('datang.jpg'),
    ))->toThrow(BusinessRuleException::class, 'Lokasi tidak terdeteksi');
});

// --- Livewire: AbsensiScan (mode lokasi) ----------------------------------

it('AbsensiScan mode lokasi: belum absen & belum ada lat/lng -> state perlu_lokasi', function () {
    Livewire::actingAs($this->teknisi)
        ->test(AbsensiScan::class)
        ->assertSet('state', 'perlu_lokasi')
        ->assertSee('Absen dari Sini');
});

it('AbsensiScan mode lokasi: setLokasi dalam radius -> state siap_datang', function () {
    Livewire::actingAs($this->teknisi)
        ->test(AbsensiScan::class)
        ->call('setLokasi', -6.2000100, 106.8166000)
        ->assertSet('state', 'siap_datang')
        ->assertSet('lokasiError', null);
});

it('AbsensiScan mode lokasi: setLokasi di luar radius -> tetap perlu_lokasi, lokasiError terisi', function () {
    Livewire::actingAs($this->teknisi)
        ->test(AbsensiScan::class)
        ->call('setLokasi', -6.3000000, 106.8166000)
        ->assertSet('state', 'perlu_lokasi')
        ->assertSee('Kantor Pusat');
});

it('AbsensiScan mode lokasi: submit catatDatang lengkap end-to-end', function () {
    Livewire::actingAs($this->teknisi)
        ->test(AbsensiScan::class)
        ->call('setLokasi', -6.2000100, 106.8166000)
        ->set('foto', UploadedFile::fake()->image('datang.jpg'))
        ->call('catatDatang')
        ->assertHasNoErrors();

    expect(DailyAttendance::query()->where('user_id', $this->teknisi->id)->whereNotNull('jam_datang')->exists())->toBeTrue();
});

// --- Filament: AttendanceLocationResource ---------------------------------

it('halaman Lokasi Absensi bisa diakses Owner/Admin/HR/Finance, bukan Teknisi', function (string $role, bool $boleh) {
    $user = ($this->mkUser)($role);

    $response = $this->actingAs($user)->get(AttendanceLocationResource::getUrl());

    $boleh ? $response->assertOk() : $response->assertForbidden();
})->with([
    'owner' => [RoleName::Owner->value, true],
    'admin' => [RoleName::Admin->value, true],
    'hr' => [RoleName::Hr->value, true],
    'finance' => [RoleName::Finance->value, true],
    'teknisi' => [RoleName::Teknisi->value, false],
]);

it('aksi "Tambah Lokasi" membuat AttendanceLocation baru', function () {
    Livewire::actingAs($this->admin)
        ->test(ListAttendanceLocations::class)
        ->callTableAction('tambahLokasi', data: [
            'nama' => 'Gudang',
            'latitude' => -6.21,
            'longitude' => 106.82,
            'radius_meter' => 75,
        ])
        ->assertHasNoTableActionErrors();

    expect(AttendanceLocation::query()->where('nama', 'Gudang')->exists())->toBeTrue();
});

it('EditAction memperbarui lokasi lewat Livewire', function () {
    Livewire::actingAs($this->admin)
        ->test(ListAttendanceLocations::class)
        ->callTableAction('edit', $this->kantor, data: [
            'nama' => 'Kantor Pusat (Baru)',
            'latitude' => -6.2000000,
            'longitude' => 106.8166000,
            'radius_meter' => 80,
        ])
        ->assertHasNoTableActionErrors();

    expect($this->kantor->fresh()->nama)->toBe('Kantor Pusat (Baru)')
        ->and($this->kantor->fresh()->radius_meter)->toBe(80);
});

// --- Filament: Pengaturan Absensi (mode_absensi) --------------------------

it('simpan mode_absensi lewat halaman Pengaturan Absensi', function () {
    Livewire::actingAs($this->admin)
        ->test(PengaturanAbsensi::class)
        ->set('modeAbsensi', 'qr')
        ->call('simpan')
        ->assertHasNoErrors();

    expect(app(AttendanceSettingService::class)->data()->mode_absensi)->toBe(AttendanceMode::Qr);
});
