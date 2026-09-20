<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\ServiceCatalog;
use App\Models\Team;
use App\Models\Titik;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->orderService = new OrderService;
});

function ttUser(RoleName $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('titik dipilih -> order.titik_id & jam_jadwal terisi dari titik->jam', function () {
    $admin = ttUser(RoleName::Admin);
    $customer = Customer::factory()->create();
    $alamat = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $catalog = ServiceCatalog::factory()->create();
    $titik = Titik::factory()->create(['nama' => 'Titik 1', 'jam' => '08:15:00']);

    $orders = $this->orderService->createOrders([
        'mode_pelanggan' => 'terdaftar',
        'customer_id' => $customer->id,
        'alamat' => [
            [
                'mode' => 'existing',
                'customer_address_id' => $alamat->id,
                'titik_id' => $titik->id,
                'items' => [['service_catalog_id' => $catalog->id, 'unit_mode' => 'tidak_ada']],
            ],
        ],
    ], $admin);

    expect($orders[0]->titik_id)->toBe($titik->id)
        ->and($orders[0]->jam_jadwal)->toBe('08:15:00');
});

it('titik nonaktif ditolak', function () {
    $admin = ttUser(RoleName::Admin);
    $customer = Customer::factory()->create();
    $alamat = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $catalog = ServiceCatalog::factory()->create();
    $titik = Titik::factory()->create(['aktif' => false]);

    expect(fn () => $this->orderService->createOrders([
        'mode_pelanggan' => 'terdaftar',
        'customer_id' => $customer->id,
        'alamat' => [
            [
                'mode' => 'existing',
                'customer_address_id' => $alamat->id,
                'titik_id' => $titik->id,
                'items' => [['service_catalog_id' => $catalog->id, 'unit_mode' => 'tidak_ada']],
            ],
        ],
    ], $admin))->toThrow(BusinessRuleException::class, 'nonaktif');
});

it('titik yg tidak ada ditolak', function () {
    $admin = ttUser(RoleName::Admin);
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
                'titik_id' => 99999,
                'items' => [['service_catalog_id' => $catalog->id, 'unit_mode' => 'tidak_ada']],
            ],
        ],
    ], $admin))->toThrow(BusinessRuleException::class, 'tidak ditemukan');
});

it('team_id diisi -> seluruh anggota tim tercatat, PIC = pic tim, status terjadwal', function () {
    $admin = ttUser(RoleName::Admin);
    $pic = ttUser(RoleName::Teknisi);
    $anggota = ttUser(RoleName::Teknisi);
    $team = Team::factory()->create(['aktif' => true, 'pic_teknisi_id' => $pic->id]);
    $team->members()->sync([$pic->id, $anggota->id]);

    $customer = Customer::factory()->create();
    $alamat = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $catalog = ServiceCatalog::factory()->create();

    $orders = $this->orderService->createOrders([
        'mode_pelanggan' => 'terdaftar',
        'customer_id' => $customer->id,
        'alamat' => [
            [
                'mode' => 'existing',
                'customer_address_id' => $alamat->id,
                'team_id' => $team->id,
                'items' => [['service_catalog_id' => $catalog->id, 'unit_mode' => 'tidak_ada']],
            ],
        ],
    ], $admin);

    $order = $orders[0];
    expect($order->teknisi_id)->toBe($pic->id)
        ->and($order->team_id)->toBe($team->id)
        ->and($order->status)->toBe(OrderStatus::Terjadwal)
        ->and($order->orderTechnicians()->pluck('teknisi_id')->sort()->values()->all())
        ->toBe(collect([$pic->id, $anggota->id])->sort()->values()->all());
});

it('team_id & teknisi_id diisi bersamaan -> ditolak', function () {
    $admin = ttUser(RoleName::Admin);
    $teknisi = ttUser(RoleName::Teknisi);
    $pic = ttUser(RoleName::Teknisi);
    $team = Team::factory()->create(['aktif' => true, 'pic_teknisi_id' => $pic->id]);
    $team->members()->sync([$pic->id]);

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
                'team_id' => $team->id,
                'teknisi_id' => $teknisi->id,
                'items' => [['service_catalog_id' => $catalog->id, 'unit_mode' => 'tidak_ada']],
            ],
        ],
    ], $admin))->toThrow(BusinessRuleException::class, 'pilih salah satu');
});

it('team_id & teknisi_id keduanya kosong -> order tetap baru tanpa PIC (regresi)', function () {
    $admin = ttUser(RoleName::Admin);
    $customer = Customer::factory()->create();
    $alamat = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $catalog = ServiceCatalog::factory()->create();

    $orders = $this->orderService->createOrders([
        'mode_pelanggan' => 'terdaftar',
        'customer_id' => $customer->id,
        'alamat' => [
            [
                'mode' => 'existing',
                'customer_address_id' => $alamat->id,
                'items' => [['service_catalog_id' => $catalog->id, 'unit_mode' => 'tidak_ada']],
            ],
        ],
    ], $admin);

    expect($orders[0]->status)->toBe(OrderStatus::Baru)
        ->and($orders[0]->teknisi_id)->toBeNull();
});

it('daftar order bisa difilter berdasarkan Tim & Tanggal Jadwal', function () {
    $admin = ttUser(RoleName::Admin);
    $team = Team::factory()->create(['aktif' => true]);

    $ordHariIniTimA = Order::factory()->create(['team_id' => $team->id, 'tanggal_jadwal' => '2026-10-01']);
    $ordHariIniTimLain = Order::factory()->create(['team_id' => null, 'tanggal_jadwal' => '2026-10-01']);
    $ordTimAHariLain = Order::factory()->create(['team_id' => $team->id, 'tanggal_jadwal' => '2026-10-02']);

    Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->filterTable('team_id', $team->id)
        ->filterTable('tanggal_jadwal', ['tanggal_jadwal' => '2026-10-01'])
        ->assertCanSeeTableRecords([$ordHariIniTimA])
        ->assertCanNotSeeTableRecords([$ordHariIniTimLain, $ordTimAHariLain]);
});
