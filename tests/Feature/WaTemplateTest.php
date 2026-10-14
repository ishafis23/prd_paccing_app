<?php

use App\Enums\RoleName;
use App\Filament\Resources\WaTemplateResource;
use App\Filament\Resources\WaTemplateResource\Pages\ListWaTemplates;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Models\WaDevice;
use App\Models\WaLog;
use App\Models\WaTemplate;
use App\Services\WaTemplateService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('render mengganti placeholder dan membuang token yang tidak terisi', function () {
    $teks = app(WaTemplateService::class)->render(
        'Halo {nama} dari {nama_usaha} {tidak_ada}',
        ['nama' => 'Budi', 'nama_usaha' => 'Paccing'],
    );

    expect($teks)->toBe('Halo Budi dari Paccing');
});

it('teks memakai default bawaan bila baris template belum ada', function () {
    $teks = app(WaTemplateService::class)->teks('berangkat', [
        'nama' => 'Budi',
        'teknisi' => 'Andi',
        'layanan' => 'Cuci AC',
        'nama_usaha' => 'Paccing',
    ]);

    expect($teks)->toContain('Budi')
        ->toContain('Andi')
        ->toContain('Cuci AC');
});

it('teks memakai isi baris template yang aktif', function () {
    WaTemplate::create(['kode' => 'berangkat', 'nama' => 'x', 'isi' => 'Halo {nama}', 'aktif' => true]);

    expect(app(WaTemplateService::class)->teks('berangkat', ['nama' => 'Sari']))->toBe('Halo Sari');
});

it('teks mengembalikan null bila template dinonaktifkan admin', function () {
    WaTemplate::create(['kode' => 'berangkat', 'nama' => 'x', 'isi' => 'Halo {nama}', 'aktif' => false]);

    expect(app(WaTemplateService::class)->teks('berangkat', ['nama' => 'Sari']))->toBeNull();
});

it('hanya Owner/Admin bisa membuka Template Pesan WA', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);

    $this->actingAs($admin);
    expect(WaTemplateResource::canViewAny())->toBeTrue();

    $this->actingAs($teknisi);
    expect(WaTemplateResource::canViewAny())->toBeFalse();
});

it('aksi "Tes Kirim" di Template Pesan WA mengirim ke nomor yang diisi', function () {
    config([
        'wa.aktif' => true,
        'wa.rate_limit' => 0,
        'wa.base_url.qr' => null,
        'wa.base_url.send' => null,
    ]);

    $provider = ProviderAccount::factory()->create([
        'base_url_qr' => 'https://gw.test',
        'base_url_send' => 'https://gw.test',
    ]);
    WaDevice::factory()->for($provider, 'providerAccount')->create([
        'connection_status' => WaDevice::KONEKSI_TERHUBUNG,
        'notif_default' => true,
    ]);

    Http::fake([
        'https://gw.test/api/qr/*' => Http::response(['status' => 'ok'], 200),
        'https://gw.test/api/send/*' => Http::response(['status' => 'terkirim'], 200),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);
    $this->actingAs($admin);

    $template = WaTemplate::create(['kode' => 'berangkat', 'nama' => 'x', 'isi' => 'Halo {nama}', 'aktif' => true]);

    Livewire::test(ListWaTemplates::class)
        ->callTableAction('tesKirim', $template, data: ['target' => '082393151414', 'pesan' => 'Halo tes'])
        ->assertHasNoTableActionErrors();

    $log = WaLog::query()->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->jenis_kirim)->toBe('tes')
        ->and($log->target)->toBe('6282393151414');
});
