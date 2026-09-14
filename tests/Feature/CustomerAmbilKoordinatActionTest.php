<?php

use App\Enums\RoleName;
use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use App\Filament\Resources\CustomerResource\RelationManagers\AddressesRelationManager;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('membuat alamat dgn latitude/longitude lewat tab Alamat tersimpan ke database', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);
    $customer = Customer::factory()->create();

    Livewire::actingAs($admin)->test(AddressesRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => EditCustomer::class,
    ])
        ->callTableAction('create', data: [
            'nama_lokasi' => 'Rumah',
            'alamat' => 'Jl Mawar 4',
            'latitude' => -5.1603181,
            'longitude' => 119.4428924,
        ])
        ->assertHasNoTableActionErrors();

    $alamat = CustomerAddress::where('customer_id', $customer->id)->sole();
    expect((float) $alamat->latitude)->toBe(-5.1603181);
    expect((float) $alamat->longitude)->toBe(119.4428924);
});
