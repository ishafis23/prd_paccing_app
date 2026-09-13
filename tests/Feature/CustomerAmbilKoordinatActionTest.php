<?php

use App\Enums\RoleName;
use App\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('tombol Ambil Koordinat mengisi latitude/longitude di form dan tersimpan ke database', function () {
    Http::fake([
        // Presisi panjang spt kasus nyata (dulu gagal krn validasi step form).
        'maps.app.goo.gl/*' => Http::response('<html>...!3d-5.1603181!4d119.44289239999999...</html>', 200),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    $response = Livewire::actingAs($admin)->test(CreateCustomer::class)
        ->fillForm([
            'nama' => 'Budi Test',
            'no_hp' => '081234567890',
            'alamat' => 'Jl Mawar 4',
            'area' => 'makassar',
            'sumber_lead' => 'whatsapp',
            'status' => 'lead',
            'maps_link' => 'https://maps.app.goo.gl/3F7MPCjWWm8fnGVa7',
        ]);

    $response->callFormComponentAction('maps_link', 'ambilKoordinat');

    $response->assertFormSet([
        'latitude' => -5.1603181,
        'longitude' => 119.4428924,
    ]);

    $response->call('create');
    $response->assertHasNoFormErrors();

    $customer = Customer::where('nama', 'Budi Test')->first();

    expect($customer)->not->toBeNull();
    expect((float) $customer->latitude)->toBe(-5.1603181);
    expect((float) $customer->longitude)->toBe(119.4428924);
});
