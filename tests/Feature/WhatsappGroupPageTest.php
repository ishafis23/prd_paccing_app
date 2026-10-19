<?php

use App\Enums\RoleName;
use App\Filament\Pages\WhatsappGroup;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Models\WaDevice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    config([
        'wa.base_url.groups' => 'https://gw.test/app/api/getgroupwa.php',
        'wa.groups_token' => null,
    ]);

    $this->mkUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };
});

it('menu Cek ID Group WA hanya untuk Owner/Admin', function () {
    $owner = ($this->mkUser)(RoleName::Owner->value);
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $teknisi = ($this->mkUser)(RoleName::Teknisi->value);

    $this->actingAs($owner);
    expect(WhatsappGroup::canAccess())->toBeTrue();
    Livewire::test(WhatsappGroup::class)->assertOk();

    $this->actingAs($admin);
    expect(WhatsappGroup::canAccess())->toBeTrue();

    $this->actingAs($teknisi);
    expect(WhatsappGroup::canAccess())->toBeFalse();
});

it('cekGroup mengisi daftar grup dari respons gateway', function () {
    $provider = ProviderAccount::factory()->create();
    $device = WaDevice::factory()->for($provider, 'providerAccount')->create();

    Http::fake([
        'https://gw.test/app/api/getgroupwa.php' => Http::response([
            'data' => ['groups' => [
                ['id' => '120363040944048443@g.us', 'subject' => 'BA AC TRI GLOBAL'],
            ]],
        ], 200),
    ]);

    $admin = ($this->mkUser)(RoleName::Admin->value);
    $this->actingAs($admin);

    Livewire::test(WhatsappGroup::class)
        ->set('deviceId', $device->id)
        ->call('cekGroup')
        ->assertHasNoErrors()
        ->assertSet('groups', [
            ['id' => '120363040944048443@g.us', 'subject' => 'BA AC TRI GLOBAL'],
        ])
        ->assertSet('sudahDicek', true);
});

it('halaman render tanpa error bila belum ada device', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $this->actingAs($admin);

    Livewire::test(WhatsappGroup::class)
        ->assertOk()
        ->assertSet('devices', []);
});

it('cekGroup menampilkan pesan error bila gateway gagal', function () {
    $provider = ProviderAccount::factory()->create();
    $device = WaDevice::factory()->for($provider, 'providerAccount')->create();

    Http::fake([
        'https://gw.test/app/api/getgroupwa.php' => Http::response([], 500),
    ]);

    $admin = ($this->mkUser)(RoleName::Admin->value);
    $this->actingAs($admin);

    Livewire::test(WhatsappGroup::class)
        ->set('deviceId', $device->id)
        ->call('cekGroup')
        ->assertSet('groups', [])
        ->assertSet('sudahDicek', true);
});
