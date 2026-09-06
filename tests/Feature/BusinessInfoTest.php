<?php

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Pages\KelolaInfoUsaha;
use App\Models\BusinessInfo;
use App\Models\User;
use App\Services\BusinessInfoService;
use App\Services\StorageQuotaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    BusinessInfoService::lupakanCache();

    $this->mkUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };
});

it('data() membuat baris default bila belum ada (B37a)', function () {
    $info = app(BusinessInfoService::class)->data();

    expect($info->nama_usaha)->toBe('Paccing Official')
        ->and(BusinessInfo::query()->count())->toBe(1);
});

it('perbarui menyimpan seluruh field & mencatat pengubah', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $service = app(BusinessInfoService::class);

    $service->perbarui([
        'nama_usaha' => 'CV Sejuk Mandiri',
        'alamat' => 'Jl. Poros No. 10, Makassar',
        'kontak_wa' => '6281199998888',
        'email' => 'halo@sejuk.test',
        'nama_pemilik' => 'Andi Owner',
    ], $admin);

    $info = $service->data();

    expect($info->nama_usaha)->toBe('CV Sejuk Mandiri')
        ->and($info->alamat)->toBe('Jl. Poros No. 10, Makassar')
        ->and($info->kontak_wa)->toBe('6281199998888')
        ->and($info->email)->toBe('halo@sejuk.test')
        ->and($info->nama_pemilik)->toBe('Andi Owner')
        ->and($info->diubah_oleh)->toBe($admin->id)
        ->and($service->namaUsaha())->toBe('CV Sejuk Mandiri');
});

it('perbarui menolak nama kosong & email tidak valid', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $service = app(BusinessInfoService::class);

    expect(fn () => $service->perbarui(['nama_usaha' => '   '], $admin))
        ->toThrow(BusinessRuleException::class, 'Nama usaha wajib');

    expect(fn () => $service->perbarui(['nama_usaha' => 'PT Uji', 'email' => 'bukan-email'], $admin))
        ->toThrow(BusinessRuleException::class, 'Email tidak valid');
});

it('perbarui hanya boleh oleh Admin/Owner', function (string $role) {
    $user = ($this->mkUser)($role);

    app(BusinessInfoService::class)->perbarui(['nama_usaha' => 'PT Uji'], $user);
})->with(['finance' => RoleName::Finance->value, 'teknisi' => RoleName::Teknisi->value])
    ->throws(\Illuminate\Auth\Access\AuthorizationException::class);

it('upload logo menyimpan file & mengganti logo lama (file lama terhapus)', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $service = app(BusinessInfoService::class);

    $logo1 = UploadedFile::fake()->image('logo1.png', 64, 64);
    $service->perbarui(['nama_usaha' => 'PT Uji'], $admin, $logo1);

    $path1 = $service->data()->logo_path;
    expect($path1)->not->toBeNull()
        ->and(Storage::disk('public')->exists($path1))->toBeTrue()
        ->and($service->logoUrl())->toContain($path1);

    $logo2 = UploadedFile::fake()->image('logo2.png', 64, 64);
    $service->perbarui(['nama_usaha' => 'PT Uji'], $admin, $logo2);

    $path2 = $service->data()->logo_path;

    expect($path2)->not->toBe($path1)
        ->and(Storage::disk('public')->exists($path2))->toBeTrue()
        ->and(Storage::disk('public')->exists($path1))->toBeFalse();
});

it('hapusLogo menghapus file & mengosongkan logo_path', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $service = app(BusinessInfoService::class);

    $logo = UploadedFile::fake()->image('logo.png', 64, 64);
    $service->perbarui(['nama_usaha' => 'PT Uji'], $admin, $logo);
    $path = $service->data()->logo_path;

    $service->perbarui(['nama_usaha' => 'PT Uji'], $admin, hapusLogo: true);

    expect($service->data()->logo_path)->toBeNull()
        ->and(Storage::disk('public')->exists($path))->toBeFalse()
        ->and($service->logoUrl())->toBeNull();
});

it('upload logo ditolak saat penyimpanan penuh (guard kuota B25)', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    config(['penyimpanan.kuota_mb' => 1]);
    StorageQuotaService::lupakanCache();
    Storage::disk('public')->put('work-reports/padat.bin', str_repeat('x', 900 * 1024));

    $logo = UploadedFile::fake()->image('logo-besar.png')->size(300);

    expect(fn () => app(BusinessInfoService::class)->perbarui(
        ['nama_usaha' => 'PT Uji'],
        $admin,
        $logo
    ))->toThrow(BusinessRuleException::class, 'Penyimpanan');
});

it('halaman Info Usaha hanya untuk Owner/Admin (B37d)', function (string $role, bool $boleh) {
    $user = ($this->mkUser)($role);

    $this->actingAs($user)->get(KelolaInfoUsaha::getUrl())
        ->{$boleh ? 'assertOk' : 'assertForbidden'}();
})->with([
    'owner' => [RoleName::Owner->value, true],
    'admin' => [RoleName::Admin->value, true],
    'finance' => [RoleName::Finance->value, false],
    'hr' => [RoleName::Hr->value, false],
    'teknisi' => [RoleName::Teknisi->value, false],
]);

it('simpan via Livewire memperbarui data usaha', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);

    Livewire::actingAs($admin)
        ->test(KelolaInfoUsaha::class)
        ->assertOk()
        ->set('namaUsaha', 'CV Sejuk Mandiri')
        ->set('alamat', 'Jl. Baru 1, Makassar')
        ->set('kontakWa', '6281299997777')
        ->set('email', 'cs@sejuk.test')
        ->set('namaPemilik', 'Budi Pemilik')
        ->call('simpan')
        ->assertHasNoErrors();

    expect(BusinessInfo::first()->nama_usaha)->toBe('CV Sejuk Mandiri')
        ->and(BusinessInfo::first()->alamat)->toBe('Jl. Baru 1, Makassar')
        ->and(BusinessInfo::first()->diubah_oleh)->toBe($admin->id);
});

it('nama usaha baru dipakai di brand login admin, title, landing, dan kop halaman (B37b/f)', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);

    app(BusinessInfoService::class)->perbarui([
        'nama_usaha' => 'Usaha Sinkron Test',
        'alamat' => 'Jl. Sinkron 77',
        'kontak_wa' => '628111222333',
    ], $admin);

    // Login admin (guest) — brand & title ikut nama usaha.
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('Usaha Sinkron Test');

    // Landing page publik menampilkan nama, alamat, dan kontak baru.
    $this->get('/')
        ->assertOk()
        ->assertSee('Usaha Sinkron Test')
        ->assertSee('Jl. Sinkron 77')
        ->assertSee('wa.me/628111222333');
});
