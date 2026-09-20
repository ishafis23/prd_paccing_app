<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('daftar order diurutkan terbaru di paling atas (defaultSort id desc)', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    $old = Order::factory()->create(['status' => OrderStatus::Terjadwal]);
    $mid = Order::factory()->create(['status' => OrderStatus::Terjadwal]);
    $new = Order::factory()->create(['status' => OrderStatus::Terjadwal]);

    $html = $this->actingAs($admin)->get('/admin/orders')->assertSuccessful()->getContent();

    expect(strpos($html, "/admin/orders/{$new->id}"))
        ->not->toBeFalse()
        ->and(strpos($html, "/admin/orders/{$new->id}"))
        ->toBeLessThan(strpos($html, "/admin/orders/{$mid->id}"))
        ->and(strpos($html, "/admin/orders/{$mid->id}"))
        ->toBeLessThan(strpos($html, "/admin/orders/{$old->id}"));
});