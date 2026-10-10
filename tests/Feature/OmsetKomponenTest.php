<?php

use App\Enums\IncomeCategory;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Livewire\Teknisi\OrderDetail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\AkuntanService;
use App\Services\FinanceService;
use App\Services\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mkUser = function (RoleName $role): User {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    };
});

it('invarian: Σ jasa + Σ material = Σ subtotal item pada beberapa order', function () {
    $admin = ($this->mkUser)(RoleName::Admin);
    $service = app(OrderService::class);

    foreach ([[3, 100000], [1, 250000], [2, 80000]] as [$jumlah, $harga]) {
        $catalog = ServiceCatalog::factory()->create(['jenis_layanan' => ServiceType::CuciAc, 'harga' => $harga]);
        $order = Order::factory()->create(['service_catalog_id' => $catalog->id, 'jumlah_unit' => $jumlah]);
        $service->tambahLayanan($order, ['nama_layanan' => 'Ganti Kapasitor', 'kategori' => null, 'harga' => 45000, 'jumlah' => 2], $admin);
        $service->tambahLayanan($order, ['nama_layanan' => 'Bongkar pasang', 'kategori' => 'instalasi', 'harga' => 60000, 'jumlah' => 1], $admin);

        $order = $order->fresh();
        $split = $order->totalPerKomponen();
        $subtotal = $order->orderItems->sum(fn (OrderItem $i) => $i->subtotal());

        expect($split['jasa'] + $split['material'])->toBe((float) $subtotal);
        expect($subtotal)->toBe($order->total());
        expect($split['material'])->toBe(90000.0);
        expect($split['jasa'])->toBe((float) ($jumlah * $harga + 60000));
    }
});

it('baris dari katalog memakai mode_omset katalog; otomatis = aturan lama', function () {
    $cuci = ServiceCatalog::factory()->create(['jenis_layanan' => ServiceType::CuciAc]);
    $pengadaan = ServiceCatalog::factory()->create(['jenis_layanan' => ServiceType::PengadaanAc]);
    $dipaksaJasa = ServiceCatalog::factory()->create(['jenis_layanan' => ServiceType::PengadaanAc, 'mode_omset' => 'jasa']);

    expect(Order::factory()->create(['service_catalog_id' => $cuci->id])->orderItems->first()->komponen)->toBe(IncomeCategory::Jasa);
    expect(Order::factory()->create(['service_catalog_id' => $pengadaan->id])->orderItems->first()->komponen)->toBe(IncomeCategory::Material);
    expect(Order::factory()->create(['service_catalog_id' => $dipaksaJasa->id])->orderItems->first()->komponen)->toBe(IncomeCategory::Jasa);
});

it('default komponen form: pengadaan/barang = material, cuci/service/instalasi = jasa', function () {
    expect(IncomeCategory::defaultUntukBaris(ServiceType::PengadaanAc, 'AC Daikin'))->toBe(IncomeCategory::Material);
    expect(IncomeCategory::defaultUntukBaris(null, 'Ganti Kapasitor'))->toBe(IncomeCategory::Material);
    expect(IncomeCategory::defaultUntukBaris(null, 'Pipa tembaga 3 meter'))->toBe(IncomeCategory::Material);
    expect(IncomeCategory::defaultUntukBaris(ServiceType::CuciAc, 'Cuci AC tambahan'))->toBe(IncomeCategory::Jasa);
    expect(IncomeCategory::defaultUntukBaris(ServiceType::Instalasi, 'Pasang unit'))->toBe(IncomeCategory::Jasa);
});

it('form teknisi menyimpan komponen terpilih dan default otomatis mengikuti nama/kategori', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi);
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);

    $this->actingAs($teknisi);

    Livewire::test(OrderDetail::class, ['order' => $order])
        ->set('layananBaruNama', 'Ganti Kapasitor')
        ->set('layananBaruKategori', 'service_ac')
        ->assertSet('layananBaruKomponen', 'material')
        ->set('layananBaruHarga', '50000')
        ->call('tambahLayanan')
        ->assertHasNoErrors();

    $item = $order->orderItems()->where('nama_layanan', 'Ganti Kapasitor')->first();
    expect($item->komponen)->toBe(IncomeCategory::Material);

    // User bisa mengganti pilihan default.
    Livewire::test(OrderDetail::class, ['order' => $order])
        ->set('layananBaruNama', 'Cuci tambahan')
        ->set('layananBaruKategori', 'cuci_ac')
        ->assertSet('layananBaruKomponen', 'jasa')
        ->set('layananBaruKomponen', 'material')
        ->set('layananBaruHarga', '10000')
        ->call('tambahLayanan');

    expect($order->orderItems()->where('nama_layanan', 'Cuci tambahan')->first()->komponen)->toBe(IncomeCategory::Material);
});

it('Owner/Admin/Finance boleh mengubah komponen baris, teknisi tidak', function () {
    $order = Order::factory()->create();
    $item = $order->orderItems()->first();
    $service = app(OrderService::class);

    foreach ([RoleName::Owner, RoleName::Admin, RoleName::Finance] as $role) {
        $service->ubahKomponenItem($item, IncomeCategory::Material, ($this->mkUser)($role));
        expect($item->fresh()->komponen)->toBe(IncomeCategory::Material);
        $service->ubahKomponenItem($item, IncomeCategory::Jasa, ($this->mkUser)($role));
        expect($item->fresh()->komponen)->toBe(IncomeCategory::Jasa);
    }

    expect(fn () => $service->ubahKomponenItem($item, IncomeCategory::Material, ($this->mkUser)(RoleName::Teknisi)))
        ->toThrow(AuthorizationException::class);
});

it('endpoint PUT order-items menolak komponen dari teknisi dan menerima dari admin', function () {
    $item = Order::factory()->create()->orderItems()->first();

    $this->actingAs(($this->mkUser)(RoleName::Teknisi))
        ->putJson(route('order-items.update', $item->id), ['komponen' => 'material'])
        ->assertStatus(403);
    expect($item->fresh()->komponen)->toBe(IncomeCategory::Jasa);

    $this->actingAs(($this->mkUser)(RoleName::Admin))
        ->putJson(route('order-items.update', $item->id), ['komponen' => 'material'])
        ->assertOk();
    expect($item->fresh()->komponen)->toBe(IncomeCategory::Material);
});

it('backfill komponen: kategori cuci/service = jasa, selainnya = material, total historis tidak berubah', function () {
    $order = Order::factory()->create(['jumlah_unit' => 1]);
    $order->orderItems()->delete();

    $baris = [
        ['cuci_ac', 100000, 2],
        ['service_ac', 150000, 1],
        ['pengadaan_ac', 4000000, 1],
        ['instalasi', 300000, 1],
        [null, 45000, 2],
    ];
    foreach ($baris as [$kategori, $harga, $jumlah]) {
        DB::table('order_items')->insert([
            'order_id' => $order->id,
            'nama_layanan' => 'Baris '.($kategori ?? 'kosong'),
            'kategori' => $kategori,
            'komponen' => 'material', // nilai default sebelum backfill
            'harga' => $harga,
            'jumlah' => $jumlah,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $totalSebelum = $order->fresh()->total();
    $jasa = $order->fresh()->orderItems->filter(fn ($i) => in_array($i->kategori?->value, ['cuci_ac', 'service_ac']));

    $migrasi = require database_path('migrations/2026_10_10_000002_add_komponen_ke_order_items_table.php');
    $migrasi->up();
    $migrasi->up(); // idempotent

    $fresh = $order->fresh();

    expect($fresh->total())->toBe($totalSebelum);
    expect($fresh->orderItems->where('komponen', IncomeCategory::Jasa)->pluck('id')->sort()->values()->all())
        ->toBe($jasa->pluck('id')->sort()->values()->all());
    expect($fresh->orderItems->where('komponen', IncomeCategory::Material))->toHaveCount(3);
    $split = $fresh->totalPerKomponen();
    expect($split['jasa'])->toBe(350000.0);
    expect($split['material'])->toBe(4000000.0 + 300000.0 + 90000.0);
});

it('koreksi total menambah baris penyesuaian jasa sehingga Order::total() = total terkoreksi', function () {
    $admin = ($this->mkUser)(RoleName::Admin);
    $catalog = ServiceCatalog::factory()->create(['jenis_layanan' => ServiceType::CuciAc, 'harga' => 100000]);
    $order = Order::factory()->create(['service_catalog_id' => $catalog->id, 'jumlah_unit' => 3]);
    $service = app(OrderService::class);

    expect($order->fresh()->total())->toBe(300000.0);

    $service->koreksiTotal($order, 250000, 'diskon', $admin);

    $fresh = $order->fresh();
    expect($fresh->total())->toBe(250000.0);
    $penyesuaian = $fresh->orderItems->firstWhere('penyesuaian', true);
    expect($penyesuaian->komponen)->toBe(IncomeCategory::Jasa);
    expect((float) $penyesuaian->harga)->toBe(-50000.0);
    // baris penyesuaian bukan "unit"
    expect($fresh->jumlahUnit())->toBe(3);
    expect($fresh->ringkasanLayanan())->not->toContain('Penyesuaian');

    // Koreksi naik juga; sumber $order yang sama (instans) ikut konsisten.
    $service->koreksiTotal($order, 400000, 'tambah biaya', $admin);
    expect($order->total())->toBe(400000.0);
    expect($order->fresh()->total())->toBe(400000.0);

    // Admin memindahkan penyesuaian ke material -> total tetap, split bergeser.
    $adj = $order->fresh()->orderItems->where('penyesuaian', true)->last();
    $service->ubahKomponenItem($adj, IncomeCategory::Material, $admin);
    $split = $order->fresh()->totalPerKomponen();
    expect($split['jasa'] + $split['material'])->toBe(400000.0);
    expect($split['material'])->toBe((float) $adj->harga);
});

it('labaRugi & pendapatan memakai komponen per baris dan total periode sama', function () {
    $admin = ($this->mkUser)(RoleName::Admin);
    $catalog = ServiceCatalog::factory()->create(['jenis_layanan' => ServiceType::CuciAc, 'harga' => 100000]);
    $order = Order::factory()->create([
        'service_catalog_id' => $catalog->id,
        'jumlah_unit' => 3,
        'status' => OrderStatus::Selesai,
    ]);
    app(OrderService::class)->tambahLayanan($order, ['nama_layanan' => 'Ganti Kapasitor', 'kategori' => null, 'harga' => 70000, 'jumlah' => 1], $admin);

    $dari = now()->startOfMonth();
    $sampai = now()->endOfMonth();

    $lr = app(FinanceService::class)->labaRugi($dari, $sampai);
    $rows = app(AkuntanService::class)->pendapatan($dari, $sampai);

    expect($lr['pendapatan'])->toBe(370000.0);
    expect($lr['pendapatan_jasa'])->toBe(300000.0);
    expect($lr['pendapatan_material'])->toBe(70000.0);
    expect($lr['pendapatan_jasa'] + $lr['pendapatan_material'])->toBe($lr['pendapatan']);
    expect($rows->first()['total'])->toBe(370000.0);
});

it('end-to-end: order 3 unit, label unit benar, split jasa/material bertambah setelah tambah baris material', function () {
    $admin = ($this->mkUser)(RoleName::Admin);
    $catalog = ServiceCatalog::factory()->create(['jenis_layanan' => ServiceType::CuciAc, 'harga' => 100000]);
    $customer = \App\Models\Customer::factory()->create();

    $order = app(OrderService::class)->createOrder([
        'customer_id' => $customer->id,
        'service_catalog_id' => $catalog->id,
        'jumlah_unit' => 3,
    ], $admin);

    expect($order->jumlahUnit())->toBe(3);
    expect($order->totalPerKomponen())->toBe(['jasa' => 300000.0, 'material' => 0.0]);

    app(OrderService::class)->tambahLayanan($order, ['nama_layanan' => 'Ganti Kapasitor', 'kategori' => null, 'harga' => 55000, 'jumlah' => 1], $admin);

    $fresh = $order->fresh();
    expect($fresh->jumlahUnit())->toBe(4);
    expect($fresh->ringkasanLayanan())->toBe('Cuci Ac · 3 unit, Ganti Kapasitor · 1 unit');
    expect($fresh->totalPerKomponen())->toBe(['jasa' => 300000.0, 'material' => 55000.0]);
    expect($fresh->total())->toBe(355000.0);
});
