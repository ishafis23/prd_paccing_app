<?php

use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function adminJumlahUnit(): User
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);

    return $user;
}

it('order dengan 3 baris item melaporkan 3 unit', function () {
    $order = Order::factory()->create(['jumlah_unit' => 1]);
    $order->orderItems()->first()->update(['nama_layanan' => 'Cuci AC']);
    OrderItem::factory()->count(2)->create(['order_id' => $order->id, 'nama_layanan' => 'Cuci AC']);

    $order = $order->fresh();

    expect($order->orderItems)->toHaveCount(3);
    expect($order->jumlahUnit())->toBe(3);
    expect($order->ringkasanLayanan())->toBe('Cuci AC · 3 unit');
});

it('satu item dengan jumlah=3 melaporkan 3 unit', function () {
    $catalog = ServiceCatalog::factory()->create(['jenis_layanan' => ServiceType::CuciAc, 'harga' => 100000]);
    $order = Order::factory()->create(['service_catalog_id' => $catalog->id, 'jumlah_unit' => 1]);
    $order->orderItems()->first()->update(['jumlah' => 3]);

    $order = $order->fresh();

    expect($order->jumlahUnit())->toBe(3);
    expect($order->ringkasanLayanan())->toBe('Cuci Ac · 3 unit');
});

it('item dibatalkan dari 3 baris tidak dihitung: 2 unit', function () {
    $order = Order::factory()->create(['jumlah_unit' => 1]);
    $order->orderItems()->first()->update(['nama_layanan' => 'Cuci AC']);
    OrderItem::factory()->count(2)->create(['order_id' => $order->id, 'nama_layanan' => 'Cuci AC']);
    $order->orderItems()->latest('id')->first()->update(['dibatalkan' => true, 'dibatalkan_pada' => now()]);

    $order = $order->fresh();

    expect($order->orderItems)->toHaveCount(3);
    expect($order->jumlahUnit())->toBe(2);
    expect($order->ringkasanLayanan())->toBe('Cuci AC · 2 unit');
});

it('ringkasan order campuran menampilkan semua layanan, bukan hanya yang pertama', function () {
    $catalog = ServiceCatalog::factory()->create(['jenis_layanan' => ServiceType::CuciAc, 'harga' => 100000]);
    $order = Order::factory()->create(['service_catalog_id' => $catalog->id, 'jumlah_unit' => 2]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'nama_layanan' => 'Ganti Kapasitor',
        'kategori' => null,
        'jumlah' => 1,
    ]);

    $order = $order->fresh();

    expect($order->ringkasanLayanan())->toBe('Cuci Ac · 2 unit, Ganti Kapasitor · 1 unit');
    expect($order->jumlahUnit())->toBe(3);
});

it('order tanpa item jatuh ke kolom jumlah_unit (fallback)', function () {
    $order = Order::factory()->create(['jumlah_unit' => 4]);
    $order->orderItems()->delete();

    expect($order->fresh()->jumlahUnit())->toBe(4);
});

it('createOrderDariUnits mengisi orders.jumlah_unit dengan jumlah unit sebenarnya dan total tetap benar', function () {
    $admin = adminJumlahUnit();
    $customer = Customer::factory()->create();
    $alamat = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $units = CustomerAcUnit::factory()->count(3)->create(['customer_id' => $customer->id, 'customer_address_id' => $alamat->id]);
    $catalog = ServiceCatalog::factory()->create(['jenis_layanan' => ServiceType::CuciAc, 'harga' => 75000]);

    $order = app(OrderService::class)->createOrderDariUnits($customer, $units->pluck('id')->all(), [
        'service_catalog_id' => $catalog->id,
    ], $admin);

    expect($order->jumlah_unit)->toBe(3);
    expect($order->jumlahUnit())->toBe(3);
    expect($order->orderItems)->toHaveCount(3);
    expect($order->total())->toBe(225000.0);
});

it('createOrders (wizard multi-alamat) mengisi jumlah_unit = Σ jumlah baris', function () {
    $admin = adminJumlahUnit();
    $customer = Customer::factory()->create();
    $alamat = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $cuci = ServiceCatalog::factory()->create(['jenis_layanan' => ServiceType::CuciAc, 'harga' => 80000]);

    $orders = app(OrderService::class)->createOrders([
        'mode_pelanggan' => 'terdaftar',
        'customer_id' => $customer->id,
        'alamat' => [[
            'mode' => 'existing',
            'customer_address_id' => $alamat->id,
            'items' => [
                ['service_catalog_id' => $cuci->id, 'jumlah' => 2],
                ['service_catalog_id' => $cuci->id, 'jumlah' => 1],
            ],
        ]],
    ], $admin);

    expect($orders)->toHaveCount(1);
    expect($orders[0]->jumlah_unit)->toBe(3);
    expect($orders[0]->jumlahUnit())->toBe(3);
    expect($orders[0]->total())->toBe(240000.0);
});

it('migrasi backfill mengisi jumlah_unit dari Σ item aktif, idempotent, tanpa mengubah total', function () {
    $order = Order::factory()->create(['jumlah_unit' => 1]);
    OrderItem::factory()->count(2)->create(['order_id' => $order->id, 'jumlah' => 2]);
    $order->orderItems()->latest('id')->first()->update(['dibatalkan' => true]);
    $totalSebelum = $order->fresh()->total();

    DB::table('orders')->where('id', $order->id)->update(['jumlah_unit' => 1]);

    $migrasi = require database_path('migrations/2026_10_10_000001_backfill_jumlah_unit_orders.php');
    $migrasi->up();
    $migrasi->up();

    $fresh = $order->fresh();
    $aktif = (int) $fresh->orderItems->where('dibatalkan', false)->sum('jumlah');

    expect($fresh->jumlah_unit)->toBe($aktif);
    expect($fresh->total())->toBe($totalSebelum);
});
