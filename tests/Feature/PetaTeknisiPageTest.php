<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Filament\Pages\PetaTeknisi;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function ptUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('halaman peta teknisi hanya bisa diakses admin/finance/hr, bukan teknisi', function (string $role, bool $boleh) {
    $user = ptUser($role);

    $this->actingAs($user)->get(PetaTeknisi::getUrl())
        ->{$boleh ? 'assertOk' : 'assertForbidden'}();
})->with([
    ['admin', true],
    ['finance', true],
    ['hr', true],
    ['teknisi', false],
]);

it('daftar teknisi bertugas hanya berisi teknisi dengan order menuju_lokasi/dikerjakan', function () {
    $admin = ptUser(RoleName::Admin->value);
    $teknisiAktif = ptUser(RoleName::Teknisi->value);
    $teknisiIdle = ptUser(RoleName::Teknisi->value);

    $customer = Customer::factory()->create(['nama' => 'Budi Makmur']);
    Order::factory()->create([
        'customer_id' => $customer->id,
        'teknisi_id' => $teknisiAktif->id,
        'status' => OrderStatus::MenujuLokasi,
    ]);
    Order::factory()->create([
        'teknisi_id' => $teknisiIdle->id,
        'status' => OrderStatus::Selesai,
    ]);

    $response = $this->actingAs($admin)->get(PetaTeknisi::getUrl());

    $response->assertOk()
        ->assertSee($teknisiAktif->name)
        ->assertDontSee($teknisiIdle->name);
});

it('teknisi tanpa sinyal gps tetap muncul di daftar dengan status belum ada sinyal', function () {
    $admin = ptUser(RoleName::Admin->value);
    $teknisi = ptUser(RoleName::Teknisi->value);

    Order::factory()->create([
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::Dikerjakan,
    ]);

    $this->actingAs($admin)->get(PetaTeknisi::getUrl())
        ->assertOk()
        ->assertSee($teknisi->name)
        ->assertSee('Belum ada sinyal GPS');
});

it('teknisi dengan sinyal gps terbaru tampil live di daftar & peta', function () {
    $admin = ptUser(RoleName::Admin->value);
    $teknisi = ptUser(RoleName::Teknisi->value);
    $teknisi->update([
        'last_latitude' => -5.147665,
        'last_longitude' => 119.432732,
        'last_location_at' => now(),
    ]);

    Order::factory()->create([
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::MenujuLokasi,
    ]);

    $this->actingAs($admin)->get(PetaTeknisi::getUrl())
        ->assertOk()
        ->assertSee($teknisi->name)
        ->assertSee('Live');
});
