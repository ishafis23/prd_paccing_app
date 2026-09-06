<?php

use App\Enums\RoleName;
use App\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Models\Customer;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);
});

it('admin bisa melihat daftar customer', function () {
    Customer::factory()->count(3)->create();

    Livewire::actingAs($this->admin)
        ->test(ListCustomers::class)
        ->assertSuccessful();
});

it('admin bisa membuat customer baru lewat form', function () {
    Livewire::actingAs($this->admin)
        ->test(CreateCustomer::class)
        ->fillForm([
            'nama' => 'Budi Santoso',
            'no_hp' => '081234567890',
            'alamat' => 'Jl. Sudirman No. 1',
            'area' => 'makassar',
            'sumber_lead' => 'whatsapp',
            'status' => 'lead',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Customer::where('nama', 'Budi Santoso')->exists())->toBeTrue();
});

it('finance tidak boleh membuat customer baru (403)', function () {
    $finance = User::factory()->create();
    $finance->assignRole(RoleName::Finance->value);

    Livewire::actingAs($finance)
        ->test(CreateCustomer::class)
        ->assertForbidden();
});

it('admin bisa mengubah data customer', function () {
    $customer = Customer::factory()->create(['nama' => 'Nama Lama']);

    Livewire::actingAs($this->admin)
        ->test(EditCustomer::class, ['record' => $customer->getRouteKey()])
        ->fillForm(['nama' => 'Nama Baru'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($customer->fresh()->nama)->toBe('Nama Baru');
});
