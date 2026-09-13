<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Filament\Pages\OrderanHarian;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function ohUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('halaman orderan harian hanya bisa diakses admin/finance/hr, bukan teknisi', function (string $role, bool $boleh) {
    $user = ohUser($role);

    $this->actingAs($user)->get(OrderanHarian::getUrl())
        ->{$boleh ? 'assertOk' : 'assertForbidden'}();
})->with([
    ['admin', true],
    ['finance', true],
    ['hr', true],
    ['teknisi', false],
]);

it('default menampilkan order hari ini, bukan tanggal lain', function () {
    $admin = ohUser(RoleName::Admin->value);

    $customerHariIni = Customer::factory()->create(['nama' => 'Budi Hari Ini']);
    Order::factory()->create([
        'customer_id' => $customerHariIni->id,
        'tanggal_jadwal' => now()->toDateString(),
        'status' => OrderStatus::Terjadwal,
    ]);

    $customerBesok = Customer::factory()->create(['nama' => 'Sari Besok']);
    Order::factory()->create([
        'customer_id' => $customerBesok->id,
        'tanggal_jadwal' => now()->addDay()->toDateString(),
        'status' => OrderStatus::Terjadwal,
    ]);

    $response = $this->actingAs($admin)->get(OrderanHarian::getUrl());

    $response->assertOk()
        ->assertSee('Budi Hari Ini')
        ->assertDontSee('Sari Besok');
});

it('pindah tanggal via property tanggal menampilkan order tanggal tsb', function () {
    $admin = ohUser(RoleName::Admin->value);

    $customer = Customer::factory()->create(['nama' => 'Customer Lusa']);
    $tanggalLusa = now()->addDays(2)->toDateString();
    Order::factory()->create([
        'customer_id' => $customer->id,
        'tanggal_jadwal' => $tanggalLusa,
        'status' => OrderStatus::Terjadwal,
    ]);

    Livewire::actingAs($admin)
        ->test(OrderanHarian::class)
        ->assertDontSee('Customer Lusa')
        ->set('tanggal', $tanggalLusa)
        ->assertSee('Customer Lusa');
});

it('ringkasan status & badge laporan tampil sesuai data order', function () {
    $admin = ohUser(RoleName::Admin->value);
    $teknisi = ohUser(RoleName::Teknisi->value);

    $customer = Customer::factory()->create(['nama' => 'PT Kalla Uji']);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'teknisi_id' => $teknisi->id,
        'tanggal_jadwal' => now()->toDateString(),
        'status' => OrderStatus::Selesai,
    ]);
    \App\Models\WorkReport::factory()->create([
        'order_id' => $order->id,
        'teknisi_id' => $teknisi->id,
    ]);

    Livewire::actingAs($admin)
        ->test(OrderanHarian::class)
        ->assertSee('PT Kalla Uji')
        ->assertSee($teknisi->name)
        ->assertSee('Menunggu')
        ->assertSee('Selesai: 1');
});

it('tombol Hari Ini mengembalikan ke tanggal sekarang', function () {
    $admin = ohUser(RoleName::Admin->value);

    Livewire::actingAs($admin)
        ->test(OrderanHarian::class)
        ->set('tanggal', now()->addDays(3)->toDateString())
        ->call('hariIni')
        ->assertSet('tanggal', now()->toDateString());
});
