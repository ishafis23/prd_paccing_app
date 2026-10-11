<?php

use App\Enums\RoleName;
use App\Filament\Pages\WhatsappDevice;
use App\Filament\Resources\WaDeviceResource;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Models\WaDevice;
use App\Services\WhatsappService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

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
