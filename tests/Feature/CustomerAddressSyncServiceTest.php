<?php

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\CustomerAddressSyncService;

/**
 * dev-plan/admin/03 (B75/B76) — sinkron `customers.alamat` (field teks
 * "Alamat Utama") ke `customer_addresses` (sumber kebenaran alamat utk
 * wizard Buat Order). Coverage utk hook-nya sendiri di
 * tests/Feature/Filament/CustomerResourceTest.php.
 */
beforeEach(function () {
    $this->service = new CustomerAddressSyncService;
});

it('syncIfMissing membuat CustomerAddress is_utama dari alamat customer', function () {
    $customer = Customer::factory()->create(['alamat' => 'Jl. Mawar 4']);
    expect($customer->addresses()->count())->toBe(0);

    $alamat = $this->service->syncIfMissing($customer);

    expect($alamat)->not->toBeNull()
        ->and($alamat->alamat)->toBe('Jl. Mawar 4')
        ->and($alamat->is_utama)->toBeTrue()
        ->and($customer->addresses()->count())->toBe(1);
});

it('syncIfMissing tidak melakukan apa-apa kalau alamat kosong', function () {
    $customer = Customer::factory()->create(['alamat' => null]);

    $alamat = $this->service->syncIfMissing($customer);

    expect($alamat)->toBeNull()
        ->and($customer->addresses()->count())->toBe(0);
});

it('syncIfMissing tidak menimpa kalau customer sudah punya customer_addresses', function () {
    $customer = Customer::factory()->create(['alamat' => 'Alamat Utama Text']);
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'alamat' => 'Alamat Asli']);

    $alamat = $this->service->syncIfMissing($customer);

    expect($alamat)->toBeNull()
        ->and($customer->addresses()->count())->toBe(1)
        ->and($customer->alamatUtama()->alamat)->toBe('Alamat Asli');
});

it('backfillMissing membackfill semua customer yg alamatnya terisi tapi belum punya customer_addresses, idempoten', function () {
    $adaAlamat = Customer::factory()->create(['alamat' => 'Sudah Sinkron']);
    CustomerAddress::factory()->create(['customer_id' => $adaAlamat->id]);

    $perluBackfill1 = Customer::factory()->create(['alamat' => 'Perlu Backfill 1']);
    $perluBackfill2 = Customer::factory()->create(['alamat' => 'Perlu Backfill 2']);
    $tanpaAlamat = Customer::factory()->create(['alamat' => null]);

    $dibuat = $this->service->backfillMissing();

    expect($dibuat)->toBe(2)
        ->and($perluBackfill1->addresses()->count())->toBe(1)
        ->and($perluBackfill2->addresses()->count())->toBe(1)
        ->and($tanpaAlamat->addresses()->count())->toBe(0)
        ->and($adaAlamat->addresses()->count())->toBe(1); // tidak dobel

    // Jalankan lagi -> tidak ada yang dibuat ulang (idempoten).
    expect($this->service->backfillMissing())->toBe(0);
});

it('artisan customers:sync-alamat-utama menjalankan backfill', function () {
    $customer = Customer::factory()->create(['alamat' => 'Alamat Lewat Command']);

    $this->artisan('customers:sync-alamat-utama')
        ->assertExitCode(0);

    expect($customer->addresses()->count())->toBe(1);
});
