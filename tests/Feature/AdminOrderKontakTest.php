<?php

use App\Enums\RoleName;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

/**
 * Kolom Telepon & WA di menu Order admin (masukan client) — admin bisa
 * langsung menghubungi customer.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);
});

it('menu Order admin punya kolom Telepon & WA', function () {
    Livewire::actingAs($this->admin)
        ->test(ListOrders::class)
        ->assertTableColumnExists('customer.no_hp')
        ->assertTableColumnExists('customer_wa');
});
