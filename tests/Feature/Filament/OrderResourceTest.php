<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ServiceCatalog;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);
    $this->finance = User::factory()->create();
    $this->finance->assignRole(RoleName::Finance->value);
    $this->teknisi = User::factory()->create();
    $this->teknisi->assignRole(RoleName::Teknisi->value);
});

it('admin membuat order baru lewat form filament -> lewat OrderService (status baru)', function () {
    $customer = Customer::factory()->create();
    $catalog = ServiceCatalog::factory()->create(['aktif' => true]);

    Livewire::actingAs($this->admin)
        ->test(CreateOrder::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'service_catalog_id' => $catalog->id,
            'jumlah_unit' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = Order::first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe(OrderStatus::Baru);
    expect($order->created_by)->toBe($this->admin->id);
});

it('katalog nonaktif ditolak lewat notifikasi, bukan exception mentah', function () {
    $customer = Customer::factory()->create();
    $catalog = ServiceCatalog::factory()->create(['aktif' => false]);

    Livewire::actingAs($this->admin)
        ->test(CreateOrder::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'service_catalog_id' => $catalog->id,
            'jumlah_unit' => 1,
        ])
        ->call('create');

    expect(Order::count())->toBe(0);
});

it('admin assign teknisi lewat aksi tabel -> order jadi terjadwal', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Baru, 'teknisi_id' => null]);

    Livewire::actingAs($this->admin)
        ->test(ListOrders::class)
        ->callTableAction('assignTeknisi', $order, data: ['teknisi_ids' => [$this->teknisi->id]]);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Terjadwal);
    expect($order->teknisi_id)->toBe($this->teknisi->id);
    expect($order->orderTechnicians()->where('teknisi_id', $this->teknisi->id)->exists())->toBeTrue();
});

it('assign beberapa teknisi: pertama jadi PIC, lainnya anggota tim', function () {
    $kedua = User::factory()->create();
    $kedua->assignRole(RoleName::Teknisi->value);
    $order = Order::factory()->create(['status' => OrderStatus::Baru, 'teknisi_id' => null]);

    Livewire::actingAs($this->admin)
        ->test(ListOrders::class)
        ->callTableAction('assignTeknisi', $order, data: ['teknisi_ids' => [$this->teknisi->id, $kedua->id]]);

    $order->refresh();
    expect($order->teknisi_id)->toBe($this->teknisi->id) // PIC = pertama
        ->and($order->orderTechnicians()->pluck('teknisi_id')->all())
        ->toContain($this->teknisi->id, $kedua->id);
});

it('finance tidak melihat aksi assign teknisi', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Baru]);

    Livewire::actingAs($this->finance)
        ->test(ListOrders::class)
        ->assertTableActionHidden('assignTeknisi', $order);
});

it('finance bisa catat pembayaran lunas lewat aksi tabel', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Dikerjakan]);

    Livewire::actingAs($this->finance)
        ->test(ListOrders::class)
        ->callTableAction('catatPembayaran', $order, data: [
            'metode' => 'cash',
            'jumlah_dibayar' => $order->total(),
            'tanggal_bayar' => now()->toDateString(),
        ]);

    expect($order->fresh()->status)->toBe(OrderStatus::Selesai);
    expect($order->incomes()->count())->toBe(1);
});

it('admin batalkan order baru lewat aksi tabel', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Baru]);

    Livewire::actingAs($this->admin)
        ->test(ListOrders::class)
        ->callTableAction('batalkan', $order, data: ['alasan' => 'Customer batal']);

    expect($order->fresh()->status)->toBe(OrderStatus::Batal);
});

it('aksi batalkan disembunyikan untuk order yang sudah dikerjakan', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Dikerjakan]);

    Livewire::actingAs($this->admin)
        ->test(ListOrders::class)
        ->assertTableActionHidden('batalkan', $order);
});

it('halaman detail order (infolist) tampil sukses', function () {
    $order = Order::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertSuccessful();
});

it('halaman detail order tetap tampil walau metode_dipilih terisi (regresi enum)', function () {
    $order = Order::factory()->create(['metode_dipilih' => PaymentMethod::Qris]);

    Livewire::actingAs($this->admin)
        ->test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Qris');
});
