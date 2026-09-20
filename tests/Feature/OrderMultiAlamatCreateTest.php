<?php

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->orderService = new OrderService;
});

function mokUser(RoleName $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('customer baru + 2 alamat, tiap alamat beda item -> 2 order, 1 customer, 2 alamat', function () {
    $admin = mokUser(RoleName::Admin);
    $catalogCuci = ServiceCatalog::factory()->create(['harga' => 90000]);
    $catalogService = ServiceCatalog::factory()->create(['harga' => 150000]);

    $orders = $this->orderService->createOrders([
        'mode_pelanggan' => 'baru',
        'pelanggan_baru' => ['nama' => 'Budi', 'no_hp' => '0812111', 'email' => null],
        'alamat' => [
            [
                'mode' => 'baru',
                'alamat_baru' => ['alamat' => 'Jl. A No. 1', 'nama_lokasi' => 'Rumah'],
                'items' => [
                    ['service_catalog_id' => $catalogCuci->id, 'unit_mode' => 'baru', 'unit_baru' => ['kode_ruangan' => 'Ruang Tamu']],
                ],
            ],
            [
                'mode' => 'baru',
                'alamat_baru' => ['alamat' => 'Jl. B No. 2', 'nama_lokasi' => 'Kantor'],
                'items' => [
                    ['service_catalog_id' => $catalogService->id, 'unit_mode' => 'baru', 'unit_baru' => ['kode_ruangan' => 'Lobby']],
                ],
            ],
        ],
    ], $admin);

    expect($orders)->toHaveCount(2)
        ->and(Customer::count())->toBe(1)
        ->and(CustomerAddress::count())->toBe(2)
        ->and($orders[0]->customer_address_id)->not->toBe($orders[1]->customer_address_id);

    $alamatPertama = CustomerAddress::where('alamat', 'Jl. A No. 1')->first();
    $alamatKedua = CustomerAddress::where('alamat', 'Jl. B No. 2')->first();
    expect($alamatPertama->is_utama)->toBeTrue()
        ->and($alamatKedua->is_utama)->toBeFalse();
});

it('customer lama: campur alamat existing & alamat baru dlm satu submission', function () {
    $admin = mokUser(RoleName::Admin);
    $customer = Customer::factory()->create();
    $alamatLama = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $catalog = ServiceCatalog::factory()->create();

    $orders = $this->orderService->createOrders([
        'mode_pelanggan' => 'terdaftar',
        'customer_id' => $customer->id,
        'alamat' => [
            [
                'mode' => 'existing',
                'customer_address_id' => $alamatLama->id,
                'items' => [
                    ['service_catalog_id' => $catalog->id, 'unit_mode' => 'tidak_ada'],
                ],
            ],
            [
                'mode' => 'baru',
                'alamat_baru' => ['alamat' => 'Jl. Baru No. 9'],
                'items' => [
                    ['service_catalog_id' => $catalog->id, 'unit_mode' => 'tidak_ada'],
                ],
            ],
        ],
    ], $admin);

    expect($orders)->toHaveCount(2)
        ->and(CustomerAddress::where('customer_id', $customer->id)->count())->toBe(2)
        ->and($orders[0]->customer_address_id)->toBe($alamatLama->id);
});

it('satu alamat, unit AC campur existing & baru -> numbering AC-01/AC-02 berurutan', function () {
    $admin = mokUser(RoleName::Admin);
    $customer = Customer::factory()->create();
    $alamat = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $unitLama = CustomerAcUnit::factory()->create(['customer_id' => $customer->id, 'customer_address_id' => $alamat->id, 'kode_unit' => 'AC-01']);
    $catalog = ServiceCatalog::factory()->create();

    $orders = $this->orderService->createOrders([
        'mode_pelanggan' => 'terdaftar',
        'customer_id' => $customer->id,
        'alamat' => [
            [
                'mode' => 'existing',
                'customer_address_id' => $alamat->id,
                'items' => [
                    ['service_catalog_id' => $catalog->id, 'unit_mode' => 'existing', 'customer_ac_unit_id' => $unitLama->id],
                    ['service_catalog_id' => $catalog->id, 'unit_mode' => 'baru', 'unit_baru' => ['kode_ruangan' => 'Kamar 1']],
                    ['service_catalog_id' => $catalog->id, 'unit_mode' => 'baru', 'unit_baru' => ['kode_ruangan' => 'Kamar 2']],
                ],
            ],
        ],
    ], $admin);

    $order = $orders[0];
    expect($order->orderItems)->toHaveCount(3);

    $unitBaru = CustomerAcUnit::where('customer_address_id', $alamat->id)->orderBy('id')->pluck('kode_unit')->all();
    expect($unitBaru)->toBe(['AC-01', 'AC-02', 'AC-03']);
});

it('per alamat boleh beda teknisi & jadwal', function () {
    $admin = mokUser(RoleName::Admin);
    $teknisiA = mokUser(RoleName::Teknisi);
    $teknisiB = mokUser(RoleName::Teknisi);
    $customer = Customer::factory()->create();
    $alamatA = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $alamatB = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $catalog = ServiceCatalog::factory()->create();

    $orders = $this->orderService->createOrders([
        'mode_pelanggan' => 'terdaftar',
        'customer_id' => $customer->id,
        'alamat' => [
            [
                'mode' => 'existing',
                'customer_address_id' => $alamatA->id,
                'teknisi_id' => $teknisiA->id,
                'tanggal_jadwal' => '2026-10-01',
                'items' => [['service_catalog_id' => $catalog->id, 'unit_mode' => 'tidak_ada']],
            ],
            [
                'mode' => 'existing',
                'customer_address_id' => $alamatB->id,
                'teknisi_id' => $teknisiB->id,
                'tanggal_jadwal' => '2026-10-05',
                'items' => [['service_catalog_id' => $catalog->id, 'unit_mode' => 'tidak_ada']],
            ],
        ],
    ], $admin);

    expect($orders[0]->teknisi_id)->toBe($teknisiA->id)
        ->and($orders[0]->tanggal_jadwal->format('Y-m-d'))->toBe('2026-10-01')
        ->and($orders[1]->teknisi_id)->toBe($teknisiB->id)
        ->and($orders[1]->tanggal_jadwal->format('Y-m-d'))->toBe('2026-10-05');
});

it('per item boleh beda katalog & override harga, order.service_catalog_id = item pertama', function () {
    $admin = mokUser(RoleName::Admin);
    $customer = Customer::factory()->create();
    $alamat = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $catalogA = ServiceCatalog::factory()->create(['harga' => 90000]);
    $catalogB = ServiceCatalog::factory()->pengadaan()->create(['harga' => 3500000]);

    $orders = $this->orderService->createOrders([
        'mode_pelanggan' => 'terdaftar',
        'customer_id' => $customer->id,
        'alamat' => [
            [
                'mode' => 'existing',
                'customer_address_id' => $alamat->id,
                'items' => [
                    ['service_catalog_id' => $catalogA->id, 'unit_mode' => 'tidak_ada', 'harga' => 80000],
                    ['service_catalog_id' => $catalogB->id, 'unit_mode' => 'tidak_ada'],
                ],
            ],
        ],
    ], $admin);

    $order = $orders[0]->fresh();
    expect($order->service_catalog_id)->toBe($catalogA->id)
        ->and($order->orderItems)->toHaveCount(2)
        ->and($order->orderItems[0]->harga)->toEqualWithDelta(80000, 0.01)
        ->and($order->orderItems[1]->harga)->toEqualWithDelta(3500000, 0.01)
        ->and($order->orderItems[1]->service_catalog_id)->toBe($catalogB->id);
});

it('menolak submission tanpa alamat', function () {
    $admin = mokUser(RoleName::Admin);
    $customer = Customer::factory()->create();

    expect(fn () => $this->orderService->createOrders([
        'mode_pelanggan' => 'terdaftar',
        'customer_id' => $customer->id,
        'alamat' => [],
    ], $admin))->toThrow(BusinessRuleException::class, 'Minimal satu alamat');
});

it('menolak alamat tanpa item', function () {
    $admin = mokUser(RoleName::Admin);
    $customer = Customer::factory()->create();
    $alamat = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    expect(fn () => $this->orderService->createOrders([
        'mode_pelanggan' => 'terdaftar',
        'customer_id' => $customer->id,
        'alamat' => [
            ['mode' => 'existing', 'customer_address_id' => $alamat->id, 'items' => []],
        ],
    ], $admin))->toThrow(BusinessRuleException::class, 'minimal satu baris');
});

it('menolak katalog nonaktif di baris ke-2 & rollback total (tidak ada row tersisa)', function () {
    $admin = mokUser(RoleName::Admin);
    $catalogAktif = ServiceCatalog::factory()->create(['aktif' => true]);
    $catalogNonaktif = ServiceCatalog::factory()->create(['aktif' => false]);

    expect(fn () => $this->orderService->createOrders([
        'mode_pelanggan' => 'baru',
        'pelanggan_baru' => ['nama' => 'Tono', 'no_hp' => '0813222'],
        'alamat' => [
            [
                'mode' => 'baru',
                'alamat_baru' => ['alamat' => 'Jl. C No. 3'],
                'items' => [
                    ['service_catalog_id' => $catalogAktif->id, 'unit_mode' => 'tidak_ada'],
                    ['service_catalog_id' => $catalogNonaktif->id, 'unit_mode' => 'tidak_ada'],
                ],
            ],
        ],
    ], $admin))->toThrow(BusinessRuleException::class, 'nonaktif');

    expect(Customer::count())->toBe(0)
        ->and(Order::count())->toBe(0);
});

it('menolak unit AC milik customer/alamat lain', function () {
    $admin = mokUser(RoleName::Admin);
    $customer = Customer::factory()->create();
    $customerLain = Customer::factory()->create();
    $alamat = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $unitLain = CustomerAcUnit::factory()->create(['customer_id' => $customerLain->id]);
    $catalog = ServiceCatalog::factory()->create();

    expect(fn () => $this->orderService->createOrders([
        'mode_pelanggan' => 'terdaftar',
        'customer_id' => $customer->id,
        'alamat' => [
            [
                'mode' => 'existing',
                'customer_address_id' => $alamat->id,
                'items' => [
                    ['service_catalog_id' => $catalog->id, 'unit_mode' => 'existing', 'customer_ac_unit_id' => $unitLain->id],
                ],
            ],
        ],
    ], $admin))->toThrow(BusinessRuleException::class, 'bukan milik customer ini');
});

it('titik pertama customer baru: alamat kosong fallback ke alamat awal Step 1', function () {
    $admin = mokUser(RoleName::Admin);
    $catalog = ServiceCatalog::factory()->create();

    $orders = $this->orderService->createOrders([
        'mode_pelanggan' => 'baru',
        'pelanggan_baru' => ['nama' => 'Sari', 'no_hp' => '0812999', 'alamat' => 'Jl. Awal No. 5'],
        'alamat' => [
            [
                'mode' => 'baru',
                'alamat_baru' => [], // sengaja kosong -> fallback
                'items' => [['service_catalog_id' => $catalog->id, 'unit_mode' => 'tidak_ada']],
            ],
        ],
    ], $admin);

    expect($orders[0]->alamat_pengerjaan)->toBe('Jl. Awal No. 5')
        ->and(CustomerAddress::first()->alamat)->toBe('Jl. Awal No. 5');
});

it('mode existing dgn customer_address_id kosong -> pakai alamat utama customer', function () {
    $admin = mokUser(RoleName::Admin);
    $customer = Customer::factory()->create();
    $alamatUtama = CustomerAddress::factory()->create(['customer_id' => $customer->id, 'alamat' => 'Jl. Utama No. 1']);
    $catalog = ServiceCatalog::factory()->create();

    $orders = $this->orderService->createOrders([
        'mode_pelanggan' => 'terdaftar',
        'customer_id' => $customer->id,
        'alamat' => [
            [
                'mode' => 'existing',
                'customer_address_id' => null,
                'items' => [['service_catalog_id' => $catalog->id, 'unit_mode' => 'tidak_ada']],
            ],
        ],
    ], $admin);

    expect($orders[0]->customer_address_id)->toBe($alamatUtama->id);
});

it('menolak teknisi yg bukan role Teknisi', function () {
    $admin = mokUser(RoleName::Admin);
    $bukanTeknisi = mokUser(RoleName::Finance);
    $customer = Customer::factory()->create();
    $alamat = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $catalog = ServiceCatalog::factory()->create();

    expect(fn () => $this->orderService->createOrders([
        'mode_pelanggan' => 'terdaftar',
        'customer_id' => $customer->id,
        'alamat' => [
            [
                'mode' => 'existing',
                'customer_address_id' => $alamat->id,
                'teknisi_id' => $bukanTeknisi->id,
                'items' => [
                    ['service_catalog_id' => $catalog->id, 'unit_mode' => 'tidak_ada'],
                ],
            ],
        ],
    ], $admin))->toThrow(AuthorizationException::class);
});
