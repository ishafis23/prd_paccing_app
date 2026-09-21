<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Livewire\Teknisi\RiwayatPengerjaan;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ServiceCatalog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->teknisi = User::factory()->create();
    $this->teknisi->assignRole(RoleName::Teknisi->value);
});

it('filter cariNama hanya menampilkan order dgn nama customer yang cocok', function () {
    $andi = Customer::factory()->create(['nama' => 'Andi Wijaya']);
    $siti = Customer::factory()->create(['nama' => 'Siti Aminah']);

    Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'customer_id' => $andi->id, 'status' => OrderStatus::Selesai]);
    Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'customer_id' => $siti->id, 'status' => OrderStatus::Selesai]);

    Livewire::actingAs($this->teknisi)
        ->test(RiwayatPengerjaan::class)
        ->set('cariNama', 'Andi')
        ->assertSee('Andi Wijaya')
        ->assertDontSee('Siti Aminah');
});

it('filter tanggal hanya menampilkan order yg updated_at-nya di tanggal itu', function () {
    $customerA = Customer::factory()->create(['nama' => 'Order Tanggal A']);
    $customerB = Customer::factory()->create(['nama' => 'Order Tanggal B']);

    $orderA = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'customer_id' => $customerA->id, 'status' => OrderStatus::Selesai]);
    $orderA->updated_at = Carbon::parse('2026-09-10 10:00:00');
    $orderA->saveQuietly();

    $orderB = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'customer_id' => $customerB->id, 'status' => OrderStatus::Selesai]);
    $orderB->updated_at = Carbon::parse('2026-09-15 10:00:00');
    $orderB->saveQuietly();

    Livewire::actingAs($this->teknisi)
        ->test(RiwayatPengerjaan::class)
        ->set('tanggal', '2026-09-10')
        ->assertSee('Order Tanggal A')
        ->assertDontSee('Order Tanggal B');
});

it('filter jenisLayanan hanya menampilkan order dgn kategori katalog yang cocok', function () {
    $customerCuci = Customer::factory()->create(['nama' => 'Customer Cuci']);
    $customerService = Customer::factory()->create(['nama' => 'Customer Service']);
    $catalogCuci = ServiceCatalog::factory()->create(['jenis_layanan' => ServiceType::CuciAc]);
    $catalogService = ServiceCatalog::factory()->create(['jenis_layanan' => ServiceType::ServiceAc]);

    Order::factory()->create([
        'teknisi_id' => $this->teknisi->id, 'customer_id' => $customerCuci->id,
        'service_catalog_id' => $catalogCuci->id, 'status' => OrderStatus::Selesai,
    ]);
    Order::factory()->create([
        'teknisi_id' => $this->teknisi->id, 'customer_id' => $customerService->id,
        'service_catalog_id' => $catalogService->id, 'status' => OrderStatus::Selesai,
    ]);

    Livewire::actingAs($this->teknisi)
        ->test(RiwayatPengerjaan::class)
        ->set('jenisLayanan', ServiceType::CuciAc->value)
        ->assertSee('Customer Cuci')
        ->assertDontSee('Customer Service');
});

it('resetFilter mengosongkan semua filter & tombolnya cuma tampil kalau ada filter aktif', function () {
    $customer = Customer::factory()->create(['nama' => 'Customer Reset']);
    Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'customer_id' => $customer->id, 'status' => OrderStatus::Selesai]);

    Livewire::actingAs($this->teknisi)
        ->test(RiwayatPengerjaan::class)
        ->assertDontSee('Reset Filter')
        ->set('cariNama', 'tidak ada yg cocok')
        ->assertSee('Reset Filter')
        ->assertSee('Tidak ada yang cocok')
        ->call('resetFilter')
        ->assertSet('cariNama', '')
        ->assertDontSee('Reset Filter')
        ->assertSee('Customer Reset');
});
