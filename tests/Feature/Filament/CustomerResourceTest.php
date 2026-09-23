<?php

use App\Enums\RoleName;
use App\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
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

it('create customer dgn Alamat Utama terisi otomatis bikin 1 customer_address (dev-plan/admin/03 B75)', function () {
    Livewire::actingAs($this->admin)
        ->test(CreateCustomer::class)
        ->fillForm([
            'nama' => 'Cust Kost Dg Ramang',
            'no_hp' => '081234567891',
            'alamat' => 'Jln Dg Ramang (Kos Binabrata)',
            'area' => 'makassar',
            'sumber_lead' => 'whatsapp',
            'status' => 'lead',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $customer = Customer::where('nama', 'Cust Kost Dg Ramang')->firstOrFail();

    expect($customer->addresses()->count())->toBe(1)
        ->and($customer->alamatUtama()->alamat)->toBe('Jln Dg Ramang (Kos Binabrata)')
        ->and($customer->alamatUtama()->is_utama)->toBeTrue();
});

it('edit customer lama (0 alamat tersimpan) isi Alamat Utama -> otomatis bikin 1 customer_address', function () {
    $customer = Customer::factory()->create(['alamat' => null]);
    expect($customer->addresses()->count())->toBe(0);

    Livewire::actingAs($this->admin)
        ->test(EditCustomer::class, ['record' => $customer->getRouteKey()])
        ->fillForm(['alamat' => 'Jl. Baru Diisi Sekarang'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($customer->fresh()->addresses()->count())->toBe(1)
        ->and($customer->fresh()->alamatUtama()->alamat)->toBe('Jl. Baru Diisi Sekarang');
});

it('edit customer yg SUDAH punya customer_addresses -> tidak menambah alamat baru walau Alamat Utama diubah', function () {
    $customer = Customer::factory()->create();
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'alamat' => 'Alamat Tersimpan Asli']);

    Livewire::actingAs($this->admin)
        ->test(EditCustomer::class, ['record' => $customer->getRouteKey()])
        ->fillForm(['alamat' => 'Alamat Utama Diubah Lagi'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($customer->fresh()->addresses()->count())->toBe(1)
        ->and($customer->fresh()->alamatUtama()->alamat)->toBe('Alamat Tersimpan Asli');
});
