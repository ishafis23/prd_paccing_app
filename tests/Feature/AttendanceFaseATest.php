<?php

use App\Enums\AttendanceCodeStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Pages\PengaturanAbsensi;
use App\Filament\Resources\AttendanceCodeResource;
use App\Filament\Resources\AttendanceCodeResource\Pages\ListAttendanceCodes;
use App\Models\AttendanceCode;
use App\Models\AttendanceSetting;
use App\Models\User;
use App\Services\AttendanceCodeService;
use App\Services\AttendanceSettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    AttendanceSettingService::lupakanCache();

    $this->mkUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };
});

// --- AttendanceCodeService ---------------------------------------------

it('buatBaru membuat kode aktif & menonaktifkan kode aktif sebelumnya', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $service = app(AttendanceCodeService::class);

    $kode1 = $service->buatBaru($admin);
    expect($kode1->status)->toBe(AttendanceCodeStatus::Aktif);

    $kode2 = $service->buatBaru($admin, 'Kantor Pusat');

    expect($kode2->status)->toBe(AttendanceCodeStatus::Aktif)
        ->and($kode2->lokasi)->toBe('Kantor Pusat')
        ->and($kode1->fresh()->status)->toBe(AttendanceCodeStatus::Nonaktif)
        ->and($kode1->kode)->not->toBe($kode2->kode);
});

it('aktifkan menonaktifkan kode aktif lain (hanya 1 aktif sekaligus)', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $service = app(AttendanceCodeService::class);

    $kode1 = $service->buatBaru($admin);
    $kode2 = $service->buatBaru($admin);

    $service->aktifkan($kode1, $admin);

    expect($kode1->fresh()->status)->toBe(AttendanceCodeStatus::Aktif)
        ->and($kode2->fresh()->status)->toBe(AttendanceCodeStatus::Nonaktif);
});

it('nonaktifkan mengubah status jadi nonaktif', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $service = app(AttendanceCodeService::class);

    $kode = $service->buatBaru($admin);
    $service->nonaktifkan($kode, $admin);

    expect($kode->fresh()->status)->toBe(AttendanceCodeStatus::Nonaktif);
});

it('kodeAktifValid null untuk kode salah, nonaktif, atau kedaluwarsa', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $service = app(AttendanceCodeService::class);

    expect($service->kodeAktifValid('tidak-ada'))->toBeNull();

    $nonaktif = $service->buatBaru($admin);
    $service->nonaktifkan($nonaktif, $admin);
    expect($service->kodeAktifValid($nonaktif->kode))->toBeNull();

    $expired = AttendanceCode::create([
        'kode' => 'kode-expired',
        'status' => AttendanceCodeStatus::Aktif,
        'berlaku_sampai' => now()->subMinute(),
        'dibuat_oleh' => $admin->id,
    ]);
    expect($service->kodeAktifValid('kode-expired'))->toBeNull();

    $valid = AttendanceCode::create([
        'kode' => 'kode-valid',
        'status' => AttendanceCodeStatus::Aktif,
        'berlaku_sampai' => now()->addDay(),
        'dibuat_oleh' => $admin->id,
    ]);
    expect($service->kodeAktifValid('kode-valid')?->id)->toBe($valid->id);
});

it('validasiAtauGagal melempar BusinessRuleException untuk kode tidak valid', function () {
    expect(fn () => app(AttendanceCodeService::class)->validasiAtauGagal('ngawur'))
        ->toThrow(BusinessRuleException::class, 'tidak berlaku');
});

it('buatBaru/aktifkan/nonaktifkan hanya boleh role pengelola', function () {
    $finance = ($this->mkUser)(RoleName::Finance->value);

    app(AttendanceCodeService::class)->buatBaru($finance);
})->throws(AuthorizationException::class);

// --- AttendanceSettingService --------------------------------------------

it('data() membuat baris default dengan nilai sesuai skema Games', function () {
    $setting = app(AttendanceSettingService::class)->data();

    expect(AttendanceSetting::query()->count())->toBe(1)
        ->and(substr((string) $setting->jam_games1_batas, 0, 5))->toBe('07:35')
        ->and((float) $setting->nominal_games1)->toBe(7500.0)
        ->and((float) $setting->omset_games5_minimal)->toBe(850000.0)
        ->and($setting->minimal_titik_berdua)->toBe(9)
        ->and($setting->minimal_titik_sendiri)->toBe(5)
        ->and($setting->unit_games6_berdua)->toBe(12)
        ->and($setting->unit_games6_sendiri)->toBe(6);
});

it('perbarui menyimpan nilai baru & mengembalikan lewat data() setelah cache dilupakan', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $service = app(AttendanceSettingService::class);

    $service->perbarui(['nominal_games1' => 8000, 'jam_games1_batas' => '07:40'], $admin);

    expect((float) $service->data()->nominal_games1)->toBe(8000.0)
        ->and(substr((string) $service->data()->jam_games1_batas, 0, 5))->toBe('07:40');
});

it('perbarui hanya boleh role pengelola', function () {
    $finance = ($this->mkUser)(RoleName::Finance->value);

    app(AttendanceSettingService::class)->perbarui(['nominal_games1' => 9000], $finance);
})->throws(AuthorizationException::class);

// --- Filament: Kode Absensi resource -------------------------------------

it('halaman Kode Absensi bisa diakses Owner/Admin/HR/Finance, bukan Teknisi', function (string $role, bool $boleh) {
    $user = ($this->mkUser)($role);

    $response = $this->actingAs($user)->get(AttendanceCodeResource::getUrl());

    $boleh ? $response->assertOk() : $response->assertForbidden();
})->with([
    'owner' => [RoleName::Owner->value, true],
    'admin' => [RoleName::Admin->value, true],
    'hr' => [RoleName::Hr->value, true],
    'finance' => [RoleName::Finance->value, true],
    'teknisi' => [RoleName::Teknisi->value, false],
]);

it('aksi "Buat Kode Baru" di tabel membuat AttendanceCode baru', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);

    Livewire::actingAs($admin)
        ->test(ListAttendanceCodes::class)
        ->assertSuccessful()
        ->callTableAction('buatBaru', data: ['lokasi' => 'Kantor Pusat'])
        ->assertHasNoTableActionErrors();

    expect(AttendanceCode::query()->count())->toBe(1)
        ->and(AttendanceCode::first()->lokasi)->toBe('Kantor Pusat')
        ->and(AttendanceCode::first()->status)->toBe(AttendanceCodeStatus::Aktif);
});

it('urlAbsen (isi QR) memakai APP_URL eksplisit, bukan root request ambient (regresi insiden subfolder 18 Sep)', function () {
    config(['app.url' => 'https://mycompany.web.id/paccing/public']);

    $admin = ($this->mkUser)(RoleName::Admin->value);
    $kode = AttendanceCode::create([
        'kode' => 'ABC123',
        'status' => AttendanceCodeStatus::Aktif,
        'berlaku_sampai' => now()->addDay(),
        'dibuat_oleh' => $admin->id,
    ]);

    expect(AttendanceCodeResource::urlAbsen($kode))
        ->toBe('https://mycompany.web.id/paccing/public/teknisi/absensi/ABC123');
});

it('qrDataUri menghasilkan gambar QR PNG data-uri yang valid (regresi 500 Unknown named parameter $writer)', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $kode = AttendanceCode::create([
        'kode' => 'QR-TEST-1',
        'status' => AttendanceCodeStatus::Aktif,
        'berlaku_sampai' => now()->addDay(),
        'dibuat_oleh' => $admin->id,
    ]);

    expect(AttendanceCodeResource::qrDataUri($kode))->toStartWith('data:image/png;base64,');
});

it('aksi "Lihat QR" di tabel bekerja lewat Livewire (regresi 500 modal QR)', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $service = app(AttendanceCodeService::class);
    $kode = $service->buatBaru($admin);

    Livewire::actingAs($admin)
        ->test(ListAttendanceCodes::class)
        ->callTableAction('lihatQr', $kode)
        ->assertHasNoTableActionErrors();
});

it('aksi Aktifkan/Nonaktifkan di baris tabel bekerja lewat Livewire', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $service = app(AttendanceCodeService::class);
    $kode = $service->buatBaru($admin);

    Livewire::actingAs($admin)
        ->test(ListAttendanceCodes::class)
        ->callTableAction('nonaktifkan', $kode)
        ->assertHasNoTableActionErrors();

    expect($kode->fresh()->status)->toBe(AttendanceCodeStatus::Nonaktif);
});

// --- Filament: Pengaturan Absensi page -----------------------------------

it('halaman Pengaturan Absensi bisa diakses Owner/Admin/HR, bukan Finance/Teknisi', function (string $role, bool $boleh) {
    $user = ($this->mkUser)($role);

    $response = $this->actingAs($user)->get(PengaturanAbsensi::getUrl());

    $boleh ? $response->assertOk() : $response->assertForbidden();
})->with([
    'owner' => [RoleName::Owner->value, true],
    'admin' => [RoleName::Admin->value, true],
    'hr' => [RoleName::Hr->value, true],
    'finance' => [RoleName::Finance->value, false],
    'teknisi' => [RoleName::Teknisi->value, false],
]);

it('simpan via Livewire memperbarui attendance_settings', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);

    Livewire::actingAs($admin)
        ->test(PengaturanAbsensi::class)
        ->assertOk()
        ->set('nominalGames1', '9000')
        ->set('jamGames1Batas', '07:40')
        ->call('simpan')
        ->assertHasNoErrors();

    $setting = app(AttendanceSettingService::class)->data();

    expect((float) $setting->nominal_games1)->toBe(9000.0)
        ->and(substr((string) $setting->jam_games1_batas, 0, 5))->toBe('07:40');
});
