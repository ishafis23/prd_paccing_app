<?php

use App\Enums\CustomerJenis;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Livewire\Teknisi\OrderDetail;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\Order;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mkAdmin = function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    };

    $this->mkTeknisi = function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Teknisi->value);

        return $user;
    };
});

it('CustomerAcUnit::labelTampil menggabungkan kode unit, ruangan, & PK', function () {
    $unit = CustomerAcUnit::factory()->create(['kode_unit' => 'AC-01', 'kode_ruangan' => 'Ruang Guru', 'pk' => '1 PK']);
    $tanpaPk = CustomerAcUnit::factory()->create(['kode_unit' => 'AC-02', 'kode_ruangan' => 'Kamar Utama', 'pk' => null]);

    expect($unit->labelTampil())->toBe('AC-01 — Ruang Guru (1 PK)');
    expect($tanpaPk->labelTampil())->toBe('AC-02 — Kamar Utama');
});

it('relation manager Unit AC kini tersedia jg utk customer rumahan (bukan cuma company)', function () {
    $rumahan = Customer::factory()->create(['jenis' => CustomerJenis::Perorangan]);
    $unit = CustomerAcUnit::factory()->create(['customer_id' => $rumahan->id, 'kode_unit' => 'AC-Rumah-1']);

    expect($rumahan->acUnits)->toHaveCount(1);
    expect($rumahan->acUnits->first()->id)->toBe($unit->id);
});

it('createOrder dgn customer_ac_unit_id menautkan order & baris order_item pertama ke unit itu', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create(['jenis' => CustomerJenis::Perorangan]);
    $unit = CustomerAcUnit::factory()->create(['customer_id' => $customer->id]);
    $catalog = ServiceCatalog::factory()->create();

    $order = app(OrderService::class)->createOrder([
        'customer_id' => $customer->id,
        'service_catalog_id' => $catalog->id,
        'jumlah_unit' => 1,
        'customer_ac_unit_id' => $unit->id,
    ], $admin);

    expect($order->customer_ac_unit_id)->toBe($unit->id);
    expect($order->orderItems->first()->customer_ac_unit_id)->toBe($unit->id);
});

it('createOrder menolak unit AC yg bukan milik customer terkait', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create();
    $customerLain = Customer::factory()->create();
    $unitCustomerLain = CustomerAcUnit::factory()->create(['customer_id' => $customerLain->id]);
    $catalog = ServiceCatalog::factory()->create();

    app(OrderService::class)->createOrder([
        'customer_id' => $customer->id,
        'service_catalog_id' => $catalog->id,
        'customer_ac_unit_id' => $unitCustomerLain->id,
    ], $admin);
})->throws(BusinessRuleException::class, 'bukan milik customer');

it('createOrder tanpa customer_ac_unit_id tetap jalan normal (opsional)', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create();
    $catalog = ServiceCatalog::factory()->create();

    $order = app(OrderService::class)->createOrder([
        'customer_id' => $customer->id,
        'service_catalog_id' => $catalog->id,
    ], $admin);

    expect($order->customer_ac_unit_id)->toBeNull();
    expect($order->orderItems->first()->customer_ac_unit_id)->toBeNull();
});

it('tambahLayanan dgn customer_ac_unit_id menautkan baris baru ke unit itu', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create();
    $unit = CustomerAcUnit::factory()->create(['customer_id' => $customer->id]);
    $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => OrderStatus::Dikerjakan]);

    $item = app(OrderService::class)->tambahLayanan($order, [
        'nama_layanan' => 'Cuci AC Kamar 2',
        'harga' => 100000,
        'customer_ac_unit_id' => $unit->id,
    ], $admin);

    expect($item->customer_ac_unit_id)->toBe($unit->id);
});

it('tambahLayanan menolak unit AC yg bukan milik customer order ini', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create();
    $customerLain = Customer::factory()->create();
    $unitCustomerLain = CustomerAcUnit::factory()->create(['customer_id' => $customerLain->id]);
    $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => OrderStatus::Dikerjakan]);

    app(OrderService::class)->tambahLayanan($order, [
        'nama_layanan' => 'X',
        'harga' => 1000,
        'customer_ac_unit_id' => $unitCustomerLain->id,
    ], $admin);
})->throws(BusinessRuleException::class, 'bukan milik customer');

it('form Create Order admin bisa memilih unit AC customer', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create();
    $unit = CustomerAcUnit::factory()->create(['customer_id' => $customer->id, 'kode_unit' => 'AC-99']);
    $catalog = ServiceCatalog::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreateOrder::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'service_catalog_id' => $catalog->id,
            'jumlah_unit' => 1,
            'customer_ac_unit_id' => $unit->id,
            'jenis_pelanggan' => CustomerJenis::Perorangan->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = Order::latest('id')->first();
    expect($order->customer_ac_unit_id)->toBe($unit->id);
});

it('aksi Tambah Layanan via admin table meneruskan unit AC yg dipilih', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create();
    $unit = CustomerAcUnit::factory()->create(['customer_id' => $customer->id]);
    $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => OrderStatus::Dikerjakan]);

    Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->callTableAction('tambahLayanan', $order, data: [
            'nama_layanan' => 'Cuci AC Ruang Tamu',
            'harga' => 90000,
            'jumlah' => 1,
            'customer_ac_unit_id' => $unit->id,
        ])
        ->assertNotified();

    $itemBaru = $order->fresh()->orderItems->firstWhere('nama_layanan', 'Cuci AC Ruang Tamu');
    expect($itemBaru->customer_ac_unit_id)->toBe($unit->id);
});

it('infolist order menampilkan Unit AC di Rincian Layanan', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create();
    $unit = CustomerAcUnit::factory()->create(['customer_id' => $customer->id, 'kode_unit' => 'AC-07', 'kode_ruangan' => 'Ruang Server']);
    $order = Order::factory()->create(['customer_id' => $customer->id, 'customer_ac_unit_id' => $unit->id]);

    $this->actingAs($admin)->get("/admin/orders/{$order->id}")
        ->assertOk()
        ->assertSee('Unit AC')
        ->assertSee('AC-07 — Ruang Server');
});

it('form teknisi menampilkan label unit AC di samping nama layanan', function () {
    $teknisi = ($this->mkTeknisi)();
    $customer = Customer::factory()->create();
    $unit = CustomerAcUnit::factory()->create(['customer_id' => $customer->id, 'kode_unit' => 'AC-XX', 'kode_ruangan' => 'Ruang Meeting']);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::Dikerjakan,
        'customer_ac_unit_id' => $unit->id,
    ]);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->assertSee('AC-XX — Ruang Meeting');
});

it('surat jalan menampilkan kolom Unit AC per baris pekerjaan', function () {
    $customer = Customer::factory()->create(['jenis' => CustomerJenis::Company]);
    $unit = CustomerAcUnit::factory()->create(['customer_id' => $customer->id, 'kode_unit' => 'AC-SJ', 'kode_ruangan' => 'Lobby']);
    $order = Order::factory()->create(['customer_id' => $customer->id, 'customer_ac_unit_id' => $unit->id]);
    $order->pastikanSuratJalanToken();

    $this->get(route('surat-jalan.show', [$order->id, $order->surat_jalan_token]))
        ->assertOk()
        ->assertSee('Unit AC')
        ->assertSee('AC-SJ — Lobby');
});
