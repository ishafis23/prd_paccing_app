<?php

use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Filament\Pages\PengaturanBeranda;
use App\Filament\Resources\HeroSlideResource;
use App\Filament\Resources\LayananBerandaResource;
use App\Models\BerandaSetting;
use App\Models\HeroSlide;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\BerandaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');

    $this->mkUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };

    $this->mkKatalog = function (array $overrides = []): ServiceCatalog {
        return ServiceCatalog::factory()->create(array_merge([
            'aktif' => true,
            'harga' => 100000,
        ], $overrides));
    };
});

it('layananBeranda memakai pilihan admin; fallback semua aktif bila belum dipilih (B38d)', function () {
    $a = ($this->mkKatalog)(['jenis_layanan' => ServiceType::CuciAc, 'tampil_beranda' => false, 'urutan_beranda' => 0]);
    $b = ($this->mkKatalog)(['jenis_layanan' => ServiceType::ServiceAc, 'tampil_beranda' => false]);

    // Belum ada pilihan → fallback semua aktif.
    $layanan = app(BerandaService::class)->layananBeranda();
    expect($layanan->pluck('id'))->toContain($a->id)->toContain($b->id);

    // Pilih B saja urutan 5; A urutan 1 (tapi off) → hanya B tampil.
    $b->update(['tampil_beranda' => true, 'urutan_beranda' => 5]);
    $a->update(['tampil_beranda' => true, 'urutan_beranda' => 1]);

    $layanan = app(BerandaService::class)->layananBeranda();
    expect($layanan->pluck('id')->all())->toBe([$a->id, $b->id]);
});

it('settings() membuat baris default; simpanSettings menyimpan nilai (B38e)', function () {
    $s = app(BerandaService::class)->settings();

    expect($s->tampil_layanan)->toBeTrue()
        ->and(BerandaSetting::query()->count())->toBe(1);

    app(BerandaService::class)->simpanSettings([
        'maps_embed' => 'https://www.google.com/maps/embed?pb=abc',
        'jam_operasional' => 'Senin–Sabtu 08.00–17.00',
        'sosmed_instagram' => 'https://instagram.com/paccing',
        'tampil_peta' => false,
    ]);

    $s2 = app(BerandaService::class)->settings();
    expect($s2->maps_embed)->toContain('maps/embed')
        ->and($s2->jam_operasional)->toContain('Senin')
        ->and($s2->tampil_peta)->toBeFalse();
});

it('landing menampilkan slide hero aktif & menyembunyikan yang nonaktif (B38c)', function () {
    Storage::disk('public')->put('hero/satu.jpg', 'x');
    Storage::disk('public')->put('hero/dua.jpg', 'x');

    HeroSlide::create(['judul' => 'Slide Satu Aktif', 'gambar' => 'hero/satu.jpg', 'urutan' => 2, 'aktif' => true]);
    HeroSlide::create(['judul' => 'Slide Dua Duluan', 'gambar' => 'hero/dua.jpg', 'urutan' => 1, 'aktif' => true]);
    HeroSlide::create(['judul' => 'Slide Tiga Nonaktif', 'gambar' => 'hero/dua.jpg', 'urutan' => 0, 'aktif' => false]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Slide Dua Duluan')
        ->assertSee('Slide Satu Aktif')
        ->assertDontSee('Slide Tiga Nonaktif')
        ->assertSee('hero/dua.jpg')
        ->assertSee('hero/satu.jpg');
});

it('landing memakai hero fallback saat belum ada slide aktif', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Satu Sistem untuk Cuci & Service AC Anda');
});

it('peta tampil hanya bila embed diisi & toggle aktif', function () {
    app(BerandaService::class)->simpanSettings([
        'maps_embed' => 'https://www.google.com/maps/embed?pb=xyz',
        'tampil_peta' => true,
    ]);

    $this->get('/')->assertOk()->assertSee('maps/embed?pb=xyz');

    app(BerandaService::class)->simpanSettings(['maps_embed' => null, 'tampil_peta' => true]);

    $this->get('/')->assertOk()->assertDontSee('<iframe', false);
});

it('toggle seksi layanan menyembunyikan bagian layanan di landing', function () {
    ($this->mkKatalog)();
    app(BerandaService::class)->simpanSettings(['tampil_layanan' => false]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('Pilih Layanan Sesuai Kebutuhan');
});

it('resource Website hanya untuk Owner/Admin (B38b)', function (string $role, bool $boleh) {
    $user = ($this->mkUser)($role);
    $this->actingAs($user);

    expect(HeroSlideResource::canViewAny())->toBe($boleh)
        ->and(LayananBerandaResource::canViewAny())->toBe($boleh);

    $this->get(HeroSlideResource::getUrl('index'))->{$boleh ? 'assertOk' : 'assertForbidden'}();
})->with([
    'owner' => [RoleName::Owner->value, true],
    'admin' => [RoleName::Admin->value, true],
    'finance' => [RoleName::Finance->value, false],
    'hr' => [RoleName::Hr->value, false],
    'teknisi' => [RoleName::Teknisi->value, false],
]);

it('halaman pengaturan beranda hanya Owner/Admin & simpan via Livewire', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);

    Livewire::actingAs($admin)
        ->test(PengaturanBeranda::class)
        ->assertOk()
        ->set('jamOperasional', 'Setiap hari 08.00–20.00')
        ->set('mapsEmbed', 'https://www.google.com/maps/embed?pb=livewire')
        ->set('tampilPeta', false)
        ->call('simpan')
        ->assertHasNoErrors();

    expect(BerandaSetting::first()->jam_operasional)->toContain('Setiap hari')
        ->and(BerandaSetting::first()->tampil_peta)->toBeFalse();

    $finance = ($this->mkUser)(RoleName::Finance->value);
    Livewire::actingAs($finance)->test(PengaturanBeranda::class)->assertForbidden();
});
