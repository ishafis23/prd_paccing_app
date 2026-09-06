<?php

use App\Enums\RoleName;
use App\Filament\Widgets\RingkasanFinanceWidget;
use App\Models\Income;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

it('admin melihat widget ringkasan finance di dashboard', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);
    Income::factory()->create(['nominal' => 1000000, 'tanggal' => now()]);

    Livewire::actingAs($admin)
        ->test(RingkasanFinanceWidget::class)
        ->assertSuccessful();
});

it('teknisi tidak punya akses widget (canView false)', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    auth()->login($teknisi);

    expect(RingkasanFinanceWidget::canView())->toBeFalse();
});

it('hr tidak melihat widget finance (canView false)', function () {
    $hr = User::factory()->create();
    $hr->assignRole(RoleName::Hr->value);
    auth()->login($hr);

    expect(RingkasanFinanceWidget::canView())->toBeFalse();
});
