<?php

use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function clUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('loads customer create page with lokasi picker', function () {
    $admin = clUser(RoleName::Admin->value);

    $response = $this->actingAs($admin)->get('/admin/customers/create');

    $response->assertOk();
    $response->assertSee('Ambil Koordinat');
    $response->assertSee('Link Google Maps');
});

it('loads customer edit page and can save latitude/longitude', function () {
    $admin = clUser(RoleName::Admin->value);
    $customer = Customer::factory()->create(['latitude' => null, 'longitude' => null]);

    $this->actingAs($admin)->get("/admin/customers/{$customer->id}/edit")
        ->assertOk();

    $customer->update(['latitude' => -5.1476651, 'longitude' => 119.4327324]);

    expect($customer->fresh()->latitude)->toEqual(-5.1476651);
    expect($customer->fresh()->longitude)->toEqual(119.4327324);
});

it('teknisi melihat peta lokasi customer saat koordinat sudah diisi admin', function () {
    $teknisi = clUser(RoleName::Teknisi->value);

    $customer = Customer::factory()->create([
        'latitude' => -5.1476651,
        'longitude' => 119.4327324,
    ]);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'teknisi_id' => $teknisi->id,
    ]);

    $response = $this->actingAs($teknisi)->get("/teknisi/order/{$order->id}");

    $response->assertOk();
    $response->assertSee('Buka Rute ke Lokasi Customer');
});

it('teknisi tidak melihat peta jika lokasi customer belum diisi admin', function () {
    $teknisi = clUser(RoleName::Teknisi->value);

    $customer = Customer::factory()->create([
        'latitude' => null,
        'longitude' => null,
    ]);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'teknisi_id' => $teknisi->id,
    ]);

    $response = $this->actingAs($teknisi)->get("/teknisi/order/{$order->id}");

    $response->assertOk();
    $response->assertDontSee('Buka Rute ke Lokasi Customer');
});
