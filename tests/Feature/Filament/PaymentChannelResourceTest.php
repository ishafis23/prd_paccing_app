<?php

use App\Enums\PaymentChannelType;
use App\Enums\RoleName;
use App\Filament\Resources\PaymentChannelResource\Pages\CreatePaymentChannel;
use App\Models\PaymentChannel;
use App\Models\User;
use App\Services\PaymentChannelService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/*
 * Catatan pitfall: satu test = maksimal satu request HTTP ber-actingAs
 * (dua user beda dalam satu test kena AuthenticateSession -> redirect login).
 * Helper dibuat sebagai closure di beforeEach (bukan fungsi global) supaya
 * tidak bentrok antar file test saat full suite dijalankan.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->buatUserBerperan = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };

    $this->buatChannel = function (User $by, array $overrides = []): PaymentChannel {
        return app(PaymentChannelService::class)->create(array_merge([
            'nama' => 'QRIS Paccing Uji',
            'jenis' => PaymentChannelType::Qris->value,
            'atas_nama' => 'Paccing Official',
        ], $overrides), $by);
    };
});

it('owner dan admin boleh melihat dan mengelola channel pembayaran', function () {
    $owner = ($this->buatUserBerperan)(RoleName::Owner->value);
    $admin = ($this->buatUserBerperan)(RoleName::Admin->value);

    expect(Gate::forUser($owner)->allows('viewAny', PaymentChannel::class))->toBeTrue();
    expect(Gate::forUser($owner)->allows('create', PaymentChannel::class))->toBeTrue();
    expect(Gate::forUser($admin)->allows('viewAny', PaymentChannel::class))->toBeTrue();
    expect(Gate::forUser($admin)->allows('create', PaymentChannel::class))->toBeTrue();
});

it('finance hanya boleh melihat, tidak boleh membuat/mengubah/menghapus channel', function () {
    $finance = ($this->buatUserBerperan)(RoleName::Finance->value);
    $admin = ($this->buatUserBerperan)(RoleName::Admin->value);
    $channel = ($this->buatChannel)($admin);

    expect(Gate::forUser($finance)->allows('viewAny', PaymentChannel::class))->toBeTrue();
    expect(Gate::forUser($finance)->allows('view', $channel))->toBeTrue();
    expect(Gate::forUser($finance)->allows('create', PaymentChannel::class))->toBeFalse();
    expect(Gate::forUser($finance)->allows('update', $channel))->toBeFalse();
    expect(Gate::forUser($finance)->allows('delete', $channel))->toBeFalse();
});

it('hr dan teknisi tidak bisa melihat resource channel pembayaran', function () {
    $hr = ($this->buatUserBerperan)(RoleName::Hr->value);
    $teknisi = ($this->buatUserBerperan)(RoleName::Teknisi->value);
    $admin = ($this->buatUserBerperan)(RoleName::Admin->value);
    $channel = ($this->buatChannel)($admin);

    expect(Gate::forUser($hr)->allows('viewAny', PaymentChannel::class))->toBeFalse();
    expect(Gate::forUser($hr)->allows('view', $channel))->toBeFalse();
    expect(Gate::forUser($teknisi)->allows('viewAny', PaymentChannel::class))->toBeFalse();
});

it('halaman daftar channel pembayaran terbuka untuk admin (HTTP)', function () {
    $admin = ($this->buatUserBerperan)(RoleName::Admin->value);
    ($this->buatChannel)($admin, ['nama' => 'QRIS Toko Utama']);

    $this->actingAs($admin)
        ->get('/admin/payment-channels')
        ->assertSuccessful()
        ->assertSee('Channel Pembayaran')
        ->assertSee('QRIS Toko Utama');
});

it('halaman create channel pembayaran terbuka untuk admin (HTTP)', function () {
    $admin = ($this->buatUserBerperan)(RoleName::Admin->value);

    $this->actingAs($admin)
        ->get('/admin/payment-channels/create')
        ->assertSuccessful();
});

it('finance bisa membuka daftar channel (read-only, HTTP 200)', function () {
    $finance = ($this->buatUserBerperan)(RoleName::Finance->value);
    $admin = ($this->buatUserBerperan)(RoleName::Admin->value);
    ($this->buatChannel)($admin);

    $this->actingAs($finance)
        ->get('/admin/payment-channels')
        ->assertSuccessful()
        ->assertSee('Channel Pembayaran')
        ->assertSee('QRIS Paccing Uji');
});

it('finance tidak bisa membuka halaman create channel (HTTP 403)', function () {
    $finance = ($this->buatUserBerperan)(RoleName::Finance->value);

    $this->actingAs($finance)
        ->get('/admin/payment-channels/create')
        ->assertForbidden();
});

it('admin membuat channel lewat form filament -> lewat PaymentChannelService', function () {
    $admin = ($this->buatUserBerperan)(RoleName::Admin->value);

    Livewire::actingAs($admin)
        ->test(CreatePaymentChannel::class)
        ->fillForm([
            'nama' => 'BCA Operasional',
            'jenis' => PaymentChannelType::Bank->value,
            'atas_nama' => 'Paccing Official',
            'nama_bank' => 'BCA',
            'nomor_rekening' => '8123456789',
            'aktif' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $channel = PaymentChannel::first();
    expect($channel)->not->toBeNull()
        ->and($channel->nama)->toBe('BCA Operasional')
        ->and($channel->jenis)->toBe(PaymentChannelType::Bank)
        ->and($channel->dicatat_oleh)->toBe($admin->id);
});
