<?php

use App\Enums\CustomerJenis;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Filament\Pages\DashboardPimpinan;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Filament\Resources\TechnicianOffDayResource\Pages\ListTechnicianOffDays;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ServiceCatalog;
use App\Models\TechnicianOffDay;
use App\Models\User;
use App\Services\DashboardPimpinanService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

/**
 * Revisi 17 Nov 2026 — poin 25, 26, 27, 30, 31, 32, 33 (sisi admin).
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);

    $this->teknisi = function (string $nama): User {
        $u = User::factory()->create(['name' => $nama]);
        $u->assignRole(RoleName::Teknisi->value);

        return $u;
    };
});

// ---- 33: filter metode bayar ----
it('Payments bisa difilter metode bayar (cash/transfer)', function () {
    $cash = Payment::factory()->lunas()->create(['metode' => PaymentMethod::Cash]);
    $trf = Payment::factory()->lunas()->create(['metode' => PaymentMethod::Transfer]);

    Livewire::actingAs($this->admin)
        ->test(ListPayments::class)
        ->filterTable('metode', 'cash')
        ->assertCanSeeTableRecords([$cash])
        ->assertCanNotSeeTableRecords([$trf]);
});

// ---- 27: filter periode Jadwal OFF ----
it('Jadwal OFF bisa difilter periode mulai–sampai tanggal', function () {
    $t = ($this->teknisi)('Irsyad');
    $lama = TechnicianOffDay::create(['user_id' => $t->id, 'tanggal' => '2026-10-25', 'catatan' => 'lama']);
    $dalam = TechnicianOffDay::create(['user_id' => $t->id, 'tanggal' => '2026-11-06', 'catatan' => 'dalam']);
    $luar = TechnicianOffDay::create(['user_id' => $t->id, 'tanggal' => '2026-11-20', 'catatan' => 'luar']);

    Livewire::actingAs($this->admin)
        ->test(ListTechnicianOffDays::class)
        ->filterTable('periode', ['dari' => '2026-11-01', 'sampai' => '2026-11-15'])
        ->assertCanSeeTableRecords([$dalam])
        ->assertCanNotSeeTableRecords([$lama, $luar]);
});

// ---- 25: jenis pelanggan otomatis ----
it('pelanggan terdaftar: jenis pelanggan otomatis dari data customer, tanpa memilih', function () {
    $customer = Customer::factory()->create(['jenis' => CustomerJenis::Company]);
    $alamat = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $catalog = ServiceCatalog::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(CreateOrder::class)
        ->fillForm(['mode_pelanggan' => 'terdaftar', 'customer_id' => $customer->id])
        ->assertFormFieldIsHidden('jenis_pelanggan')
        ->assertSee('Instansi (otomatis dari data customer)')
        ->fillForm([
            'alamat' => [[
                'mode' => 'existing',
                'customer_address_id' => $alamat->id,
                'items' => [['unit_mode' => 'tidak_ada', 'service_catalog_id' => $catalog->id, 'jumlah' => 1]],
            ]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Order::latest('id')->first()->jenis_pelanggan)->toBe(CustomerJenis::Company);
});

it('pelanggan baru tetap memilih jenis pelanggan', function () {
    Livewire::actingAs($this->admin)
        ->test(CreateOrder::class)
        ->fillForm(['mode_pelanggan' => 'baru'])
        ->assertFormFieldIsVisible('jenis_pelanggan');
});

// ---- 26: Unit AC tersimpan bisa dicari ----
it('daftar Unit AC tersimpan bisa dicari (kode, ruangan, PK)', function () {
    $customer = Customer::factory()->create();
    $alamat = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $a = CustomerAcUnit::factory()->create(['customer_id' => $customer->id, 'customer_address_id' => $alamat->id, 'kode_unit' => 'AC-01', 'kode_ruangan' => 'Ruangan DPI', 'pk' => '1 PK']);
    $b = CustomerAcUnit::factory()->create(['customer_id' => $customer->id, 'customer_address_id' => $alamat->id, 'kode_unit' => 'AC-18', 'kode_ruangan' => 'AC 216 (Kelas 2C)', 'pk' => '2 PK']);

    $livewire = (object) ['data' => [
        'customer_id' => $customer->id,
        'alamat' => ['k1' => ['customer_address_id' => $alamat->id, 'items' => ['i1' => []]]],
    ]];
    $path = 'data.alamat.k1.items.i1.customer_ac_unit_id';
    $page = new CreateOrder;

    expect($page->unitAcTersimpan($path, $livewire))->toHaveCount(2)
        ->and(array_keys($page->unitAcTersimpan($path, $livewire, 'kelas 2c')))->toBe([$b->id])
        ->and(array_keys($page->unitAcTersimpan($path, $livewire, 'dpi')))->toBe([$a->id])
        ->and($page->unitAcTersimpan($path, $livewire, 'tidak ada'))->toBe([]);
});

// ---- 32: batal oleh customer & kendala ----
it('pembatalan mencatat pihak, dan kendalaBatal menghitung angka + persentase', function () {
    $tgl = CarbonImmutable::parse('2026-11-10');
    $mk = fn (array $extra = []) => Order::factory()->create(array_merge([
        'tanggal_jadwal' => $tgl->toDateString(),
        'status' => OrderStatus::Terjadwal,
    ], $extra));

    $batalCust = $mk();
    $batalInt = $mk();
    $mk(['status' => OrderStatus::Selesai]);
    $mk(['status' => OrderStatus::Selesai, 'kendala_pertama_pada' => now(), 'kendala_pihak' => 'customer']);
    $luar = $mk(['tanggal_jadwal' => '2026-12-30']);

    $svc = app(OrderService::class);
    $svc->cancel($batalCust, $this->admin, 'customer tidak jadi', OrderService::BATAL_CUSTOMER);
    $svc->cancel($batalInt, $this->admin, 'teknisi tidak tersedia', OrderService::BATAL_INTERNAL);

    expect(fn () => $svc->cancel($luar, $this->admin, null, 'ngawur'))->toThrow(App\Exceptions\BusinessRuleException::class);

    $hasil = app(DashboardPimpinanService::class)->kendalaBatal($tgl->startOfMonth(), $tgl->endOfMonth());

    expect($hasil['dijadwalkan'])->toBe(4)
        ->and($hasil['batal'])->toBe(2)
        ->and($hasil['batal_persen'])->toBe(50.0)
        ->and($hasil['batal_customer'])->toBe(1)
        ->and($hasil['batal_customer_persen'])->toBe(25.0)
        ->and($hasil['batal_internal'])->toBe(1)
        ->and($hasil['terkendala'])->toBe(1)
        ->and($hasil['terkendala_persen'])->toBe(25.0)
        ->and($hasil['terkendala_customer'])->toBe(1);
});

// ---- 31: jasa vs material per teknisi ----
it('performa teknisi memisahkan pendapatan jasa dan material + rincian jasa per layanan', function () {
    $t = ($this->teknisi)('Riki');
    $order = Order::factory()->create([
        'service_catalog_id' => ServiceCatalog::factory()->create(['harga' => 100000])->id,
        'teknisi_id' => $t->id,
        'status' => OrderStatus::Selesai,
        'tanggal_jadwal' => '2026-10-20',
    ]);
    Payment::factory()->lunas()->create([
        'order_id' => $order->id,
        'total_tagihan' => 130000,
        'tanggal_bayar' => '2026-10-20',
        'omset_jasa' => 100000,
        'omset_material' => 30000,
    ]);

    // Periode lintas bulan 16 Okt – 15 Nov (poin 30): order 20 Okt ikut.
    $data = app(DashboardPimpinanService::class)
        ->performaTeknisi(CarbonImmutable::parse('2026-10-16'), CarbonImmutable::parse('2026-11-15')->endOfDay());
    $baris = collect($data)->firstWhere('nama', 'Riki');

    expect($baris['jasa'])->toBe(100000.0)
        ->and($baris['material'])->toBe(30000.0)
        ->and($baris['rupiah'])->toBe(130000.0)
        ->and(array_sum(array_column($baris['layanan'], 'jasa')))->toBe(100000.0);

    // Di luar rentang → tidak ikut.
    $kosong = collect(app(DashboardPimpinanService::class)
        ->performaTeknisi(CarbonImmutable::parse('2026-11-16'), CarbonImmutable::parse('2026-11-30')->endOfDay()))
        ->firstWhere('nama', 'Riki');
    expect($kosong['orders'])->toBe(0);
});

// ---- 30: filter tanggal Aspek Performa di halaman ----
it('halaman Dashboard Pimpinan memakai filter tanggal pada Aspek Performa', function () {
    ($this->teknisi)('Riki');
    $label = CarbonImmutable::parse('2026-10-16')->translatedFormat('d M Y').' – '.CarbonImmutable::parse('2026-11-15')->translatedFormat('d M Y');

    Livewire::actingAs($this->admin)
        ->test(DashboardPimpinan::class)
        ->call('setTab', 'performa')
        ->set('dari', '2026-10-16')
        ->set('sampai', '2026-11-15')
        ->assertSee($label)
        ->assertSee('Pendapatan Jasa')
        ->call('setPerforma', 'kendala')
        ->assertSee('Pekerjaan Dijadwalkan')
        ->call('resetPeriodePerforma')
        ->assertSet('dari', '');
});

// ---- 31: Pengecekan jadi jenis layanan sendiri ----
it('Pengecekan tersedia sebagai jenis layanan (jasa) dan muncul terpisah di rincian per teknisi', function () {
    expect(App\Support\ServiceTypeResolver::valid('pengecekan'))->toBeTrue()
        ->and(App\Support\ServiceTypeResolver::label('pengecekan'))->toBe('Pengecekan')
        ->and(App\Support\ServiceTypeResolver::komponen('pengecekan')->value)->toBe('jasa');

    $t = ($this->teknisi)('Cek');
    $order = Order::factory()->create([
        'service_catalog_id' => ServiceCatalog::factory()->create(['harga' => 50000])->id,
        'teknisi_id' => $t->id,
        'status' => OrderStatus::Selesai,
        'tanggal_jadwal' => '2026-11-05',
    ]);
    App\Models\OrderItem::factory()->create([
        'order_id' => $order->id, 'kategori' => 'pengecekan', 'komponen' => 'jasa', 'harga' => 50000, 'jumlah' => 1,
    ]);

    $baris = collect(app(DashboardPimpinanService::class)
        ->performaTeknisi(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30')->endOfDay()))
        ->firstWhere('nama', 'Cek');

    expect($baris['layanan']['pengecekan']['jasa'])->toBe(50000.0);
});
