<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Livewire\Teknisi\OrderDetail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Models\WorkReportPhoto;
use App\Services\OrderService;
use App\Services\TeknisiService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mkAdmin = function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    };
});

it('order baru otomatis dapat satu order_item dari service_catalog/jumlah_unit', function () {
    $admin = ($this->mkAdmin)();
    $catalog = ServiceCatalog::factory()->create(['harga' => 150000]);
    $customer = Customer::factory()->create();

    $order = app(OrderService::class)->createOrder([
        'customer_id' => $customer->id,
        'service_catalog_id' => $catalog->id,
        'jumlah_unit' => 2,
    ], $admin);

    expect($order->orderItems)->toHaveCount(1);
    expect($order->orderItems->first()->harga)->toEqual(150000);
    expect($order->orderItems->first()->jumlah)->toBe(2);
    expect($order->orderItems->first()->service_catalog_id)->toBe($catalog->id);
});

it('total() order = jumlah semua order_items, konsisten dgn cara lama saat cuma 1 baris', function () {
    $catalog = ServiceCatalog::factory()->create(['harga' => 100000]);
    $order = Order::factory()->create(['service_catalog_id' => $catalog->id, 'jumlah_unit' => 3]);

    expect($order->fresh()->total())->toBe(300000.0);
});

it('order factory (dipakai ratusan test lain) tetap otomatis dapat order_item, total() tidak nol', function () {
    $order = Order::factory()->create();

    expect($order->orderItems)->toHaveCount(1);
    expect($order->fresh()->total())->toBeGreaterThan(0.0);
});

it('tambahLayanan menambah baris baru & total bertambah (alur Ada Perbaikan)', function () {
    $admin = ($this->mkAdmin)();
    $catalog = ServiceCatalog::factory()->create(['harga' => 100000]);
    $order = Order::factory()->create(['service_catalog_id' => $catalog->id, 'jumlah_unit' => 1, 'status' => OrderStatus::Dikerjakan]);

    $totalAwal = $order->fresh()->total();
    expect($totalAwal)->toBe(100000.0);

    $item = app(OrderService::class)->tambahLayanan($order, [
        'nama_layanan' => 'Ganti Kapasitor',
        'kategori' => ServiceType::ServiceAc->value,
        'harga' => 75000,
        'jumlah' => 1,
        'catatan' => 'Kapasitor lama sudah lemah',
    ], $admin);

    expect($item->nama_layanan)->toBe('Ganti Kapasitor');
    expect($item->ditambahkan_oleh)->toBe($admin->id);
    expect($order->fresh()->orderItems)->toHaveCount(2);
    expect($order->fresh()->total())->toBe(175000.0);
});

it('tambahLayanan menolak nama kosong, harga negatif, atau bukan admin/owner', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    $order = Order::factory()->create(['status' => OrderStatus::Dikerjakan]);

    expect(fn () => app(OrderService::class)->tambahLayanan($order, ['nama_layanan' => '', 'harga' => 10000], $admin))
        ->toThrow(BusinessRuleException::class, 'wajib diisi');

    expect(fn () => app(OrderService::class)->tambahLayanan($order, ['nama_layanan' => 'X', 'harga' => -1], $admin))
        ->toThrow(BusinessRuleException::class, 'tidak valid');

    expect(fn () => app(OrderService::class)->tambahLayanan($order, ['nama_layanan' => 'X', 'harga' => 10000], $teknisi))
        ->toThrow(AuthorizationException::class);
});

it('tambahLayanan BOLEH utk order selesai (klarifikasi 21 Sep 2026)', function () {
    $admin = ($this->mkAdmin)();
    $order = Order::factory()->create(['status' => OrderStatus::Selesai]);

    $item = app(OrderService::class)->tambahLayanan($order, ['nama_layanan' => 'X', 'harga' => 10000], $admin);

    expect($item->nama_layanan)->toBe('X');
});

it('tambahLayanan menolak order yg sudah batal', function () {
    $admin = ($this->mkAdmin)();
    $order = Order::factory()->create(['status' => OrderStatus::Batal]);

    app(OrderService::class)->tambahLayanan($order, ['nama_layanan' => 'X', 'harga' => 10000], $admin);
})->throws(BusinessRuleException::class);

it('aksi Tambah Layanan di tabel admin tersedia utk order aktif & selesai, tersembunyi utk batal', function () {
    $admin = ($this->mkAdmin)();
    $aktif = Order::factory()->create(['status' => OrderStatus::Dikerjakan]);
    $selesai = Order::factory()->create(['status' => OrderStatus::Selesai]);
    $batal = Order::factory()->create(['status' => OrderStatus::Batal]);

    Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->assertTableActionVisible('tambahLayanan', $aktif)
        ->assertTableActionVisible('tambahLayanan', $selesai)
        ->assertTableActionHidden('tambahLayanan', $batal);
});

it('aksi Tambah Layanan via admin table membuat order_item baru', function () {
    $admin = ($this->mkAdmin)();
    $order = Order::factory()->create(['status' => OrderStatus::Dikerjakan]);

    Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->callTableAction('tambahLayanan', $order, data: [
            'nama_layanan' => 'Tambah Freon',
            'harga' => 50000,
            'jumlah' => 1,
        ])
        ->assertNotified();

    expect($order->fresh()->orderItems)->toHaveCount(2);
});

it('aksi Tambah Layanan menampilkan peringatan hanya kalau order sudah selesai', function () {
    $admin = ($this->mkAdmin)();
    $aktif = Order::factory()->create(['status' => OrderStatus::Dikerjakan]);
    $selesai = Order::factory()->create(['status' => OrderStatus::Selesai]);

    Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->mountTableAction('tambahLayanan', $selesai)
        ->assertSee('Order ini sudah Selesai');

    Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->mountTableAction('tambahLayanan', $aktif)
        ->assertDontSee('Order ini sudah Selesai');
});

it('aksi Tambah Layanan via admin table TETAP bisa membuat order_item baru walau order sudah selesai', function () {
    $admin = ($this->mkAdmin)();
    $order = Order::factory()->create(['status' => OrderStatus::Selesai]);

    Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->callTableAction('tambahLayanan', $order, data: [
            'nama_layanan' => 'Tambahan Pasca Selesai',
            'harga' => 30000,
            'jumlah' => 1,
        ])
        ->assertNotified();

    expect($order->fresh()->orderItems)->toHaveCount(2);
});

it('halaman view order menampilkan rincian layanan', function () {
    $admin = ($this->mkAdmin)();
    $catalog = ServiceCatalog::factory()->create(['harga' => 100000]);
    $order = Order::factory()->create(['service_catalog_id' => $catalog->id]);
    app(OrderService::class)->tambahLayanan($order, ['nama_layanan' => 'Ganti Kapasitor', 'harga' => 75000], $admin);

    $this->actingAs($admin)->get("/admin/orders/{$order->id}")
        ->assertOk()
        ->assertSee('Rincian Layanan')
        ->assertSee('Ganti Kapasitor');
});

// --- Unit "tidak jadi/batal" (revisi customer) ------------------------------

it('batalkanItem mengeluarkan unit dari total, aktifkanItem mengembalikannya', function () {
    $admin = ($this->mkAdmin)();
    $order = Order::factory()->create(['status' => OrderStatus::Dikerjakan]);
    $item = $order->orderItems()->first();
    $item->update(['harga' => 100000, 'jumlah' => 2]);

    expect($order->fresh()->total())->toBe(200000.0);

    app(OrderService::class)->batalkanItem($item, $admin, 'Customer batal 1 unit');

    $item->refresh();
    expect($item->dibatalkan())->toBeTrue()
        ->and($item->dibatalkan_pada)->not->toBeNull()
        ->and($item->alasan_batal)->toBe('Customer batal 1 unit')
        ->and($order->fresh()->total())->toBe(0.0);

    app(OrderService::class)->aktifkanItem($item, $admin);

    expect($item->fresh()->dibatalkan())->toBeFalse()
        ->and($order->fresh()->total())->toBe(200000.0);
});

it('unit yang dibatalkan tidak menuntut foto wajib & tidak masuk tipe foto per layanan', function () {
    $admin = ($this->mkAdmin)();
    $order = Order::factory()->create(['status' => OrderStatus::Selesai]);
    $item = $order->orderItems()->first();
    $item->update(['kategori' => ServiceType::CuciAc, 'nama_layanan' => 'Cuci AC']);

    $service = app(TeknisiService::class);
    expect($service->tipeFotoPerLayananWajib($order->fresh('orderItems')))->toContain('cuci')
        ->and($service->fotoWajibKurang($order->fresh('orderItems')))->not->toBe([]);

    app(OrderService::class)->batalkanItem($item, $admin);

    $orderFresh = $order->fresh('orderItems');
    expect($service->fotoWajibKurang($orderFresh))->toBe([])
        ->and($service->tipeFotoPerLayananWajib($orderFresh))->toBe([]);
});

it('batalkanItem menolak teknisi yang bukan anggota order', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    $order = Order::factory()->create(['status' => OrderStatus::Dikerjakan]);
    $item = $order->orderItems()->first();

    app(OrderService::class)->batalkanItem($item, $teknisi);
})->throws(AuthorizationException::class);

it('teknisi bisa menandai unit tidak jadi lewat OrderDetail', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);
    $item = $order->orderItems()->first();

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->call('toggleItemBatal', $item->id)
        ->assertOk();

    expect($item->fresh()->dibatalkan())->toBeTrue();
});

it('teknisi dapat menambah layanan saat order dikerjakan (baris + grup foto)', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);
    $sebelum = $order->orderItems()->count();

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->set('layananBaruNama', 'Tambah Cuci AC')
        ->set('layananBaruKategori', ServiceType::CuciAc->value)
        ->set('layananBaruHarga', '75000')
        ->set('layananBaruJumlah', 1)
        ->call('tambahLayanan')
        ->assertRedirect();

    $order->refresh();
    expect($order->orderItems()->count())->toBe($sebelum + 1);

    $baru = $order->orderItems()->latest('id')->first();
    expect($baru->nama_layanan)->toBe('Tambah Cuci AC')
        ->and($baru->kategori)->toBe(ServiceType::CuciAc)
        ->and((float) $baru->harga)->toBe(75000.0)
        ->and($baru->ditambahkan_oleh)->toBe($teknisi->id);
});

it('teknisi tidak bisa menambah layanan sebelum berangkat (terjadwal)', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Terjadwal]);

    app(OrderService::class)->tambahLayananOlehTeknisi($order, $teknisi, [
        'nama_layanan' => 'Cuci tambahan',
        'kategori' => ServiceType::CuciAc->value,
        'harga' => 50000,
    ]);
})->throws(BusinessRuleException::class, 'menuju lokasi');

it('teknisi bukan anggota tidak bisa menambah layanan', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    $lain = User::factory()->create();
    $lain->assignRole(RoleName::Teknisi->value);
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);

    app(OrderService::class)->tambahLayananOlehTeknisi($order, $lain, [
        'nama_layanan' => 'Cuci tambahan',
        'kategori' => ServiceType::CuciAc->value,
        'harga' => 50000,
    ]);
})->throws(AuthorizationException::class);

it('teknisi dapat menghapus baris layanan yang belum difoto', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);
    $item = $order->orderItems()->first();

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->call('hapusLayanan', $item->id)
        ->assertRedirect();

    expect($order->orderItems()->whereKey($item->id)->exists())->toBeFalse();
});

it('teknisi tidak bisa menghapus baris layanan yang sudah punya foto', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);
    $item = $order->orderItems()->first();

    $report = $order->workReports()->create([
        'teknisi_id' => $teknisi->id,
        'catatan_pengerjaan' => 'x',
        'waktu_mulai' => now(),
        'waktu_selesai' => now(),
    ]);
    WorkReportPhoto::create([
        'work_report_id' => $report->id,
        'order_item_id' => $item->id,
        'slot' => 'foto_tampak_depan_lokasi',
        'path' => 'work-reports/x.jpg',
        'urutan' => 0,
    ]);

    app(OrderService::class)->hapusLayananOlehTeknisi($item, $teknisi);
})->throws(BusinessRuleException::class, 'sudah punya foto');
