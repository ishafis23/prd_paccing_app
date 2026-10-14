<?php

use App\Enums\RoleName;
use App\Filament\Pages\WhatsappDevice;
use App\Filament\Resources\WaDeviceResource;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Models\WaDevice;
use App\Models\WaLog;
use App\Services\WhatsappService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Pin config WA agar test tidak bergantung pada .env developer.
    config([
        'wa.aktif' => false,
        'wa.rate_limit' => 0,
        'wa.pdf_secret' => null,
        'wa.document_token' => null,
        'wa.base_url.qr' => null,
        'wa.base_url.send' => null,
        'wa.base_url.group' => null,
        'wa.base_url.document' => null,
        'wa.base_url.reset' => null,
    ]);

    $this->mkUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };
});

it('getQr mendeteksi QR image sebagai "belum terhubung"', function () {
    $provider = ProviderAccount::factory()->create(['base_url_qr' => 'https://gw.test']);
    $device = WaDevice::factory()->for($provider, 'providerAccount')->create();

    Http::fake([
        'https://gw.test/api/qr/*' => Http::response([
            'qr' => 'data:image/png;base64,AAAA',
            'message' => 'scan me',
        ], 200),
    ]);

    $hasil = app(WhatsappService::class)->qr($device);

    expect($hasil['ok'])->toBeTrue()
        ->and($hasil['has_qr'])->toBeTrue()
        ->and($hasil['image'])->toBe('data:image/png;base64,AAAA');
});

it('status menandai terhubung bila ok tanpa QR, lalu sinkron kolom device', function () {
    $provider = ProviderAccount::factory()->create(['base_url_qr' => 'https://gw.test']);
    $device = WaDevice::factory()->for($provider, 'providerAccount')->create([
        'connection_status' => WaDevice::KONEKSI_BELUM,
    ]);

    Http::fake([
        'https://gw.test/api/qr/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $hasil = app(WhatsappService::class)->status($device);

    expect($hasil['terhubung'])->toBeTrue()
        ->and($device->fresh()->connection_status)->toBe(WaDevice::KONEKSI_TERHUBUNG)
        ->and($device->fresh()->last_checked_at)->not->toBeNull();
});

it('status menandai belum terhubung bila gateway mengembalikan QR', function () {
    $provider = ProviderAccount::factory()->create(['base_url_qr' => 'https://gw.test']);
    $device = WaDevice::factory()->for($provider, 'providerAccount')->create([
        'connection_status' => WaDevice::KONEKSI_TERHUBUNG,
    ]);

    Http::fake([
        'https://gw.test/api/qr/*' => Http::response(['base64' => 'iVBORw0KGgo='], 200),
    ]);

    app(WhatsappService::class)->status($device);

    expect($device->fresh()->connection_status)->toBe(WaDevice::KONEKSI_BELUM);
});

it('resetSession memakai endpoint api.php + Bearer token dan payload reset', function () {
    config(['wa.document_token' => 'tok123']);

    $provider = ProviderAccount::factory()->create([
        'base_url_document' => 'https://gw.test/apiwablasted/api_document.php',
    ]);
    $device = WaDevice::factory()->for($provider, 'providerAccount')->create([
        'device_name' => 'nurulfikri3',
    ]);

    Http::fake([
        'https://gw.test/apiwablasted/api.php' => Http::response('Session berhasil dihapus nurulfikri3', 200),
    ]);

    $hasil = app(WhatsappService::class)->resetSession($device);

    expect($hasil['ok'])->toBeTrue();

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/apiwablasted/api.php')
            && $request['device'] === 'nurulfikri3'
            && $request['action'] === 'reset_session'
            && $request->hasHeader('Authorization', 'Bearer tok123');
    });
});

it('normalizeNumber menormalkan 0-awal dan prefix 62', function () {
    $svc = app(WhatsappService::class);

    expect($svc->normalizeNumber('0812-3456-7890'))->toBe('6281234567890')
        ->and($svc->normalizeNumber('62812 3456 7890'))->toBe('6281234567890')
        ->and($svc->normalizeNumber('8123456789'))->toBe('628123456789')
        ->and($svc->normalizeNumber(''))->toBe('');
});

it('mengembalikan error yang jelas bila base URL QR belum dikonfigurasi', function () {
    $provider = ProviderAccount::factory()->create(['base_url_qr' => null]);
    $device = WaDevice::factory()->for($provider, 'providerAccount')->create();

    $hasil = app(WhatsappService::class)->qr($device);

    expect($hasil['ok'])->toBeFalse()
        ->and($hasil['error'])->toContain('Base URL QR');
});

it('menjaga users.wa_device_id konsisten dengan assigned_user_id', function () {
    $device = WaDevice::factory()->create();
    $userA = ($this->mkUser)(RoleName::Admin->value);

    $device->assigned_user_id = $userA->id;
    $device->save();
    expect($userA->fresh()->wa_device_id)->toBe($device->id);

    // Di-assign ke user lain → user lama dilepas.
    $userB = ($this->mkUser)(RoleName::Admin->value);
    $device->assigned_user_id = $userB->id;
    $device->save();

    expect($userB->fresh()->wa_device_id)->toBe($device->id)
        ->and($userA->fresh()->wa_device_id)->toBeNull();

    // Device dihapus → pemakai dilepas.
    $device->delete();
    expect($userB->fresh()->wa_device_id)->toBeNull();
});

it('menu Perangkat & Tes WA hanya untuk Owner/Admin', function () {
    $owner = ($this->mkUser)(RoleName::Owner->value);
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $teknisi = ($this->mkUser)(RoleName::Teknisi->value);

    $this->actingAs($owner);
    expect(WhatsappDevice::canAccess())->toBeTrue();
    Livewire::test(WhatsappDevice::class)->assertOk();

    $this->actingAs($admin);
    expect(WhatsappDevice::canAccess())->toBeTrue();

    $this->actingAs($teknisi);
    expect(WhatsappDevice::canAccess())->toBeFalse()
        ->and(WaDeviceResource::canViewAny())->toBeFalse();
});

it('resource Perangkat WhatsApp bisa dilihat Owner/Admin', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $this->actingAs($admin);

    expect(WaDeviceResource::canViewAny())->toBeTrue();
});

it('kirimTes dengan radio "grup" mengirim ke JID grup apa adanya', function () {
    config(['wa.aktif' => true]);

    $provider = ProviderAccount::factory()->create([
        'base_url_qr' => 'https://gw.test',
        'base_url_send' => 'https://gw.test',
    ]);
    $device = WaDevice::factory()->for($provider, 'providerAccount')->create([
        'connection_status' => WaDevice::KONEKSI_TERHUBUNG,
    ]);

    Http::fake([
        'https://gw.test/api/qr/*' => Http::response(['status' => 'ok'], 200),
        'https://gw.test/api/send/*' => Http::response(['status' => 'terkirim'], 200),
    ]);

    $admin = ($this->mkUser)(RoleName::Admin->value);
    $this->actingAs($admin);

    $jid = '120363023284398181@g.us';

    Livewire::test(WhatsappDevice::class)
        ->set('tesDeviceId', $device->id)
        ->set('tesJenis', 'grup')
        ->set('tesGrup', $jid)
        ->set('tesPesan', 'Halo grup')
        ->call('kirimTes')
        ->assertHasNoErrors();

    $log = WaLog::query()->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('send-grup')
        ->and($log->target)->toBe($jid);
});

it('kirimTes dengan radio "pribadi" menormalkan nomor tujuan', function () {
    config(['wa.aktif' => true]);

    $provider = ProviderAccount::factory()->create([
        'base_url_qr' => 'https://gw.test',
        'base_url_send' => 'https://gw.test',
    ]);
    $device = WaDevice::factory()->for($provider, 'providerAccount')->create([
        'connection_status' => WaDevice::KONEKSI_TERHUBUNG,
    ]);

    Http::fake([
        'https://gw.test/api/qr/*' => Http::response(['status' => 'ok'], 200),
        'https://gw.test/api/send/*' => Http::response(['status' => 'terkirim'], 200),
    ]);

    $admin = ($this->mkUser)(RoleName::Admin->value);
    $this->actingAs($admin);

    Livewire::test(WhatsappDevice::class)
        ->set('tesDeviceId', $device->id)
        ->set('tesJenis', 'pribadi')
        ->set('tesTarget', '0812-3456-7890')
        ->set('tesPesan', 'Halo')
        ->call('kirimTes')
        ->assertHasNoErrors();

    $log = WaLog::query()->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('send-teks')
        ->and($log->target)->toBe('6281234567890');
});
