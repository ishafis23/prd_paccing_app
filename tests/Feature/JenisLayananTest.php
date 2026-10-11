<?php

use App\Enums\IncomeCategory;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Models\Order;
use App\Models\ServiceCatalog;
use App\Models\ServiceTypeOption;
use App\Models\User;
use App\Services\OmsetService;
use App\Services\OrderService;
use App\Support\ServiceTypeResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;

/**
 * Menu "Jenis Layanan" tambahan (dev-plan/26): jenis baru di luar enum bawaan
 * bisa dipakai di order & dikenali laporan omset.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);
});

it('opsi jenis layanan = bawaan (enum) + tambahan (tabel)', function () {
    ServiceTypeOption::create(['kode' => 'ganti_kompresor', 'label' => 'Ganti Kompresor', 'komponen' => 'material', 'aktif' => true]);
    ServiceTypeOption::create(['kode' => 'nonaktif_x', 'label' => 'Nonaktif', 'komponen' => 'jasa', 'aktif' => false]);

    $opsi = ServiceTypeResolver::options();

    expect($opsi)->toHaveKey('cuci_ac')          // bawaan
        ->and($opsi)->toHaveKey('ganti_kompresor') // tambahan
        ->and($opsi)->not->toHaveKey('nonaktif_x'); // nonaktif disembunyikan

    expect(ServiceTypeResolver::label('ganti_kompresor'))->toBe('Ganti Kompresor');
    expect(ServiceTypeResolver::valid('ganti_kompresor'))->toBeTrue();
    expect(ServiceTypeResolver::valid('tidak_ada'))->toBeFalse();
});

it('order bisa memakai jenis layanan tambahan & komponen mengikuti setelan', function () {
    ServiceTypeOption::create(['kode' => 'ganti_kompresor', 'label' => 'Ganti Kompresor', 'komponen' => 'material', 'aktif' => true]);

    $catalog = ServiceCatalog::factory()->create(['jenis_layanan' => ServiceType::CuciAc, 'harga' => 100000]);
    $order = Order::factory()->create(['service_catalog_id' => $catalog->id, 'jumlah_unit' => 1]);

    app(OrderService::class)->tambahLayanan($order, [
        'nama_layanan' => 'Ganti Kompresor',
        'kategori' => 'ganti_kompresor',
        'harga' => 500000,
        'jumlah' => 1,
    ], $this->admin);

    $item = $order->fresh()->orderItems->firstWhere('nama_layanan', 'Ganti Kompresor');

    expect($item->kategori->value)->toBe('ganti_kompresor');
    expect($item->komponenOmset())->toBe(IncomeCategory::Material);
});

it('laporan omset mengenali jenis layanan tambahan', function () {
    ServiceTypeOption::create(['kode' => 'ganti_kompresor', 'label' => 'Ganti Kompresor', 'komponen' => 'material', 'aktif' => true]);

    $order = Order::factory()->create([
        'status' => OrderStatus::Selesai,
        'tanggal_jadwal' => now()->toDateString(),
    ]);
    $order->orderItems()->delete();

    app(OrderService::class)->tambahLayanan($order, [
        'nama_layanan' => 'Ganti Kompresor',
        'kategori' => 'ganti_kompresor',
        'harga' => 500000,
        'jumlah' => 1,
    ], $this->admin);

    $omset = app(OmsetService::class);
    $rows = collect($omset->ringkasan(Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()));

    expect($rows->firstWhere('kategori', 'ganti_kompresor'))->not->toBeNull();
    expect($omset->total($rows->all())['material'])->toBeGreaterThanOrEqual(500000.0);
});
