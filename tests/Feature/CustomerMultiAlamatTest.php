<?php

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\CustomerAddress;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->orderService = new OrderService;
});

function maUser(RoleName $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('alamat pertama otomatis jadi utama, alamat kedua tidak', function () {
    $customer = Customer::factory()->create();

    $pertama = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $kedua = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    expect($pertama->fresh()->is_utama)->toBeTrue()
        ->and($kedua->fresh()->is_utama)->toBeFalse()
        ->and($customer->alamatUtama()->id)->toBe($pertama->id);
});

it('createOrder dgn alamat terpilih mengisi customer_address_id & snapshot alamat_pengerjaan', function () {
    $admin = maUser(RoleName::Admin);
    $customer = Customer::factory()->create(['alamat' => 'Alamat Customer Lama']);
    $alamat = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'alamat' => 'Jl. Rumah Baru No. 9',
    ]);
    $catalog = ServiceCatalog::factory()->create();

    $order = $this->orderService->createOrder([
        'customer_id' => $customer->id,
        'service_catalog_id' => $catalog->id,
        'customer_address_id' => $alamat->id,
    ], $admin);

    expect($order->customer_address_id)->toBe($alamat->id)
        ->and($order->alamat_pengerjaan)->toBe('Jl. Rumah Baru No. 9');
});

it('createOrder menolak alamat milik customer lain', function () {
    $admin = maUser(RoleName::Admin);
    $customer = Customer::factory()->create();
    $customerLain = Customer::factory()->create();
    $alamatLain = CustomerAddress::factory()->create(['customer_id' => $customerLain->id]);
    $catalog = ServiceCatalog::factory()->create();

    expect(fn () => $this->orderService->createOrder([
        'customer_id' => $customer->id,
        'service_catalog_id' => $catalog->id,
        'customer_address_id' => $alamatLain->id,
    ], $admin))->toThrow(BusinessRuleException::class, 'bukan milik customer ini');
});

it('createOrder menolak unit AC yg berada di alamat berbeda', function () {
    $admin = maUser(RoleName::Admin);
    $customer = Customer::factory()->create();
    $alamatA = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $alamatB = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $unitB = CustomerAcUnit::factory()->create([
        'customer_id' => $customer->id,
        'customer_address_id' => $alamatB->id,
    ]);
    $catalog = ServiceCatalog::factory()->create();

    expect(fn () => $this->orderService->createOrder([
        'customer_id' => $customer->id,
        'service_catalog_id' => $catalog->id,
        'customer_address_id' => $alamatA->id,
        'customer_ac_unit_id' => $unitB->id,
    ], $admin))->toThrow(BusinessRuleException::class, 'tidak berada di alamat');
});

it('createOrderDariUnits membuat satu order dgn order_items per unit di alamat sama', function () {
    $admin = maUser(RoleName::Admin);
    $customer = Customer::factory()->create();
    $alamat = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'alamat' => 'Jl. Usaha No. 2',
    ]);
    $unit1 = CustomerAcUnit::factory()->create(['customer_id' => $customer->id, 'customer_address_id' => $alamat->id]);
    $unit2 = CustomerAcUnit::factory()->create(['customer_id' => $customer->id, 'customer_address_id' => $alamat->id]);
    $catalog = ServiceCatalog::factory()->create(['harga' => 90000]);

    $order = $this->orderService->createOrderDariUnits(
        $customer,
        [$unit1->id, $unit2->id],
        ['service_catalog_id' => $catalog->id],
        $admin,
    );

    expect($order->customer_address_id)->toBe($alamat->id)
        ->and($order->alamat_pengerjaan)->toBe('Jl. Usaha No. 2')
        ->and($order->orderItems)->toHaveCount(2)
        ->and($order->orderItems->pluck('customer_ac_unit_id')->sort()->values()->all())
        ->toBe(collect([$unit1->id, $unit2->id])->sort()->values()->all());
});

it('createOrderDariUnits menolak unit dari lebih dari satu alamat', function () {
    $admin = maUser(RoleName::Admin);
    $customer = Customer::factory()->create();
    $alamatA = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $alamatB = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $unitA = CustomerAcUnit::factory()->create(['customer_id' => $customer->id, 'customer_address_id' => $alamatA->id]);
    $unitB = CustomerAcUnit::factory()->create(['customer_id' => $customer->id, 'customer_address_id' => $alamatB->id]);
    $catalog = ServiceCatalog::factory()->create();

    expect(fn () => $this->orderService->createOrderDariUnits(
        $customer,
        [$unitA->id, $unitB->id],
        ['service_catalog_id' => $catalog->id],
        $admin,
    ))->toThrow(BusinessRuleException::class, 'SATU alamat');
});
