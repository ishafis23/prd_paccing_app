<?php

use App\Enums\DailyAttendanceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReminderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Filament\Pages\DashboardPimpinan;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\DailyAttendance;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ServiceCatalog;
use App\Models\ServiceReminder;
use App\Models\StockItem;
use App\Models\User;
use App\Services\DashboardPimpinanService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->seed(Database\Seeders\RolesAndPermissionsSeeder::class);
});

function dpUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** Order selesai dgn harga layanan utama tertentu, lunas pada tanggal tsb. */
function dpOrderLunas(string $tanggal, int $harga, array $extra = []): Order
{
    $order = Order::factory()->create(array_merge([
        'service_catalog_id' => ServiceCatalog::factory()->create(['harga' => $harga])->id,
        'status' => OrderStatus::Selesai,
    ], $extra));

    Payment::factory()->lunas()->create([
        'order_id' => $order->id,
        'total_tagihan' => $harga,
        'tanggal_bayar' => $tanggal,
    ]);

    return $order;
}

function dpBulan(): array
{
    $now = CarbonImmutable::now();

    return [$now->startOfMonth(), $now->endOfMonth()];
}

it('hanya owner/admin yang boleh akses Dashboard Pimpinan', function (string $role, bool $boleh) {
    $this->actingAs(dpUser($role))->get(DashboardPimpinan::getUrl())
        ->{$boleh ? 'assertOk' : 'assertForbidden'}();
})->with([
    ['owner', true],
    ['admin', true],
    ['finance', false],
    ['hr', false],
    ['teknisi', false],
]);

it('ringkasan customer menghitung total per status & baru bulan ini', function () {
    Customer::factory()->create(['status' => 'aktif']);
    Customer::factory()->create(['status' => 'aktif']);
    Customer::factory()->create(['status' => 'lead']);
    Customer::factory()->create(['status' => 'nonaktif']);

    [$awal, $akhir] = dpBulan();
    $data = app(DashboardPimpinanService::class)->ringkasanCustomer($awal, $akhir);

    expect($data['total'])->toBe(4)
        ->and($data['aktif'])->toBe(2)
        ->and($data['lead'])->toBe(1)
        ->and($data['nonaktif'])->toBe(1)
        ->and($data['baru_bulan_ini'])->toBe(4);
});

it('unit per customer menghitung total, rata-rata, dan top customer', function () {
    $a = Customer::factory()->create(['nama' => 'A']);
    $b = Customer::factory()->create(['nama' => 'B']);
    CustomerAcUnit::factory()->count(3)->create(['customer_id' => $a->id]);
    CustomerAcUnit::factory()->create(['customer_id' => $b->id]);

    $data = app(DashboardPimpinanService::class)->unitPerCustomer();

    expect($data['total_unit'])->toBe(4)
        ->and($data['customer_punya_unit'])->toBe(2)
        ->and($data['rata_rata'])->toBe(2.0)
        ->and($data['top'][0]['nama'])->toBe('A')
        ->and($data['top'][0]['jumlah_unit'])->toBe(3);
});

it('follow-up memisah perlu, pending, dan terlaksana', function () {
    [$awal, $akhir] = dpBulan();
    $tanggal = CarbonImmutable::now()->toDateString();

    ServiceReminder::factory()->create(['tanggal_servis_berikutnya' => $tanggal, 'status_notice' => ReminderStatus::BelumJatuhTempo]);
    ServiceReminder::factory()->create(['tanggal_servis_berikutnya' => $tanggal, 'status_notice' => ReminderStatus::SiapDihubungi]);
    ServiceReminder::factory()->create(['tanggal_servis_berikutnya' => $tanggal, 'status_notice' => ReminderStatus::SudahDihubungi]);
    ServiceReminder::factory()->create(['tanggal_servis_berikutnya' => $tanggal, 'status_notice' => ReminderStatus::Selesai]);

    $data = app(DashboardPimpinanService::class)->followUp($awal, $akhir);

    expect($data['total'])->toBe(4)
        ->and($data['perlu_difollow_up'])->toBe(2)
        ->and($data['sudah_pending'])->toBe(1)
        ->and($data['terlaksana'])->toBe(1);
});

it('keuangan menghitung pendapatan, pengeluaran, dan laba/rugi', function () {
    [$awal, $akhir] = dpBulan();
    dpOrderLunas(CarbonImmutable::now()->toDateString(), 100000);
    Expense::factory()->create(['tanggal' => CarbonImmutable::now()->toDateString(), 'nominal' => 30000]);

    $data = app(DashboardPimpinanService::class)->keuangan($awal, $akhir);

    expect($data['pendapatan'])->toBe(100000.0)
        ->and($data['pendapatan_jasa'])->toBe(100000.0)
        ->and($data['pengeluaran'])->toBe(30000.0)
        ->and($data['laba_rugi'])->toBe(70000.0);
});

it('klasifikasi pengerjaan mengelompok cuci, service, dan pemasangan + rinciannya', function () {
    [$awal, $akhir] = dpBulan();

    $order = Order::factory()->create([
        'service_catalog_id' => ServiceCatalog::factory()->create(['harga' => 100000])->id,
        'status' => OrderStatus::Selesai,
    ]);
    // baris utama otomatis: CuciAc, 1 unit, 100.000
    OrderItem::factory()->create(['order_id' => $order->id, 'kategori' => ServiceType::ServiceAc, 'harga' => 200000, 'jumlah' => 1]);
    OrderItem::factory()->create(['order_id' => $order->id, 'kategori' => ServiceType::Instalasi, 'harga' => 500000, 'jumlah' => 2]);

    $data = app(DashboardPimpinanService::class)->klasifikasiPengerjaan($awal, $akhir);

    expect($data['cuci']['unit'])->toBe(1)
        ->and($data['cuci']['rupiah'])->toBe(100000.0)
        ->and($data['service']['unit'])->toBe(1)
        ->and($data['service']['rupiah'])->toBe(200000.0)
        ->and($data['pemasangan']['unit'])->toBe(2)
        ->and($data['pemasangan']['rupiah'])->toBe(1000000.0)
        ->and(collect($data['service']['items'])->firstWhere('label', 'Service AC')['unit'])->toBe(1)
        ->and(collect($data['pemasangan']['items'])->firstWhere('label', 'Instalasi')['rupiah'])->toBe(1000000.0);
});

it('rekap pekanan mengelompokkan pendapatan per pekan Senin-Minggu', function () {
    $awal = CarbonImmutable::parse('2026-09-01');
    $akhir = CarbonImmutable::parse('2026-09-30');

    dpOrderLunas('2026-09-01', 100000); // pekan 31 Agu–06 Sep
    dpOrderLunas('2026-09-03', 50000);  // pekan yang sama
    dpOrderLunas('2026-09-10', 70000);  // pekan berikutnya

    $rekap = app(DashboardPimpinanService::class)->pendapatanPekanan($awal, $akhir);

    expect($rekap)->toHaveCount(2)
        ->and($rekap->first()['total'])->toBe(150000.0)
        ->and($rekap->last()['total'])->toBe(70000.0);
});

it('laba/rugi harian dan pekanan = pendapatan dikurangi pengeluaran', function () {
    $awal = CarbonImmutable::parse('2026-09-01');
    $akhir = CarbonImmutable::parse('2026-09-30');

    dpOrderLunas('2026-09-02', 100000);
    Expense::factory()->create(['tanggal' => '2026-09-02', 'nominal' => 30000]);
    dpOrderLunas('2026-09-08', 200000);

    $service = app(DashboardPimpinanService::class);
    $harian = $service->labaRugiHarian($awal, $akhir);
    $pekanan = $service->labaRugiPekanan($awal, $akhir);

    $tgl2 = $harian->firstWhere('tanggal', '2026-09-02');
    expect($tgl2['pendapatan'])->toBe(100000.0)
        ->and($tgl2['pengeluaran'])->toBe(30000.0)
        ->and($tgl2['laba_rugi'])->toBe(70000.0)
        ->and($pekanan->sum('laba_rugi'))->toBe(270000.0);
});

it('performa teknisi menghitung order, unit, dan rupiah', function () {
    [$awal, $akhir] = dpBulan();
    $teknisi = User::factory()->create(['name' => 'Teknisi A']);
    $teknisi->assignRole(RoleName::Teknisi->value);

    dpOrderLunas(CarbonImmutable::now()->toDateString(), 100000, ['teknisi_id' => $teknisi->id]);
    dpOrderLunas(CarbonImmutable::now()->toDateString(), 150000, ['teknisi_id' => $teknisi->id]);

    $data = app(DashboardPimpinanService::class)->performaTeknisi($awal, $akhir);
    $baris = collect($data)->firstWhere('nama', 'Teknisi A');

    expect($baris['orders'])->toBe(2)
        ->and($baris['unit'])->toBe(2)
        ->and($baris['rupiah'])->toBe(250000.0);
});

it('kehadiran teknisi merekap hadir, normal, bonus, dan telat', function () {
    [$awal, $akhir] = dpBulan();
    $teknisi = User::factory()->create(['name' => 'Teknisi H']);
    $teknisi->assignRole(RoleName::Teknisi->value);
    $awalBulan = CarbonImmutable::now()->startOfMonth();

    DailyAttendance::create(['user_id' => $teknisi->id, 'tanggal' => $awalBulan->toDateString(), 'status_datang' => DailyAttendanceStatus::Normal]);
    DailyAttendance::create(['user_id' => $teknisi->id, 'tanggal' => $awalBulan->addDay()->toDateString(), 'status_datang' => DailyAttendanceStatus::Telat]);
    DailyAttendance::create(['user_id' => $teknisi->id, 'tanggal' => $awalBulan->addDays(2)->toDateString(), 'status_datang' => DailyAttendanceStatus::Bonus]);

    $data = app(DashboardPimpinanService::class)->kehadiranTeknisi($awal, $akhir);
    $baris = collect($data)->firstWhere('nama', 'Teknisi H');

    expect($baris['hadir'])->toBe(3)
        ->and($baris['normal'])->toBe(1)
        ->and($baris['telat'])->toBe(1)
        ->and($baris['bonus'])->toBe(1);
});

it('neraca menghitung kas, persediaan, piutang, dan ekuitas', function () {
    dpOrderLunas(CarbonImmutable::now()->toDateString(), 100000);
    Expense::factory()->create(['tanggal' => CarbonImmutable::now()->toDateString(), 'nominal' => 30000]);
    StockItem::factory()->create(['aktif' => true, 'stok_saat_ini' => 10, 'harga_beli' => 5000]);

    Order::factory()->create([
        'service_catalog_id' => ServiceCatalog::factory()->create(['harga' => 200000])->id,
        'status' => OrderStatus::Selesai,
    ]); // tanpa pembayaran = piutang

    $data = app(DashboardPimpinanService::class)->neraca();

    expect($data['kas'])->toBe(70000.0)
        ->and($data['persediaan'])->toBe(50000.0)
        ->and($data['piutang'])->toBe(200000.0)
        ->and($data['total_aset'])->toBe(320000.0)
        ->and($data['ekuitas'])->toBe(320000.0);
});

it('arus kas menghitung masuk, keluar, dan saldo', function () {
    [$awal, $akhir] = dpBulan();
    $hari = CarbonImmutable::now()->toDateString();

    dpOrderLunas($hari, 100000);
    Expense::factory()->create(['tanggal' => $hari, 'nominal' => 30000]);

    $data = app(DashboardPimpinanService::class)->arusKas($awal, $akhir);

    expect($data['masuk'])->toBe(100000.0)
        ->and($data['keluar'])->toBe(30000.0)
        ->and($data['bersih'])->toBe(70000.0)
        ->and($data['saldo_awal'])->toBe(0.0)
        ->and($data['saldo_akhir'])->toBe(70000.0);
});

it('halaman menampilkan tab aspek dan sub-tab keuangan', function () {
    $admin = dpUser(RoleName::Admin->value);
    dpOrderLunas(CarbonImmutable::now()->toDateString(), 100000);

    Livewire\Livewire::actingAs($admin)->test(DashboardPimpinan::class)
        ->assertSee('Aspek Customer')
        ->assertSee('Aspek Keuangan')
        ->assertSee('Aspek Performa')
        ->assertSee('Total Customer')
        ->assertSee('Segera hadir') // placeholder area kecamatan
        ->call('setTab', 'keuangan')
        ->assertSet('tab', 'keuangan')
        ->assertSee('2.1 Pendapatan')
        ->assertSet('keuangan', 'pendapatan')
        ->assertSee('Rp 100.000')
        ->call('setKeuangan', 'laba_rugi')
        ->assertSee('Laba/Rugi Harian')
        ->call('setTab', 'performa')
        ->assertSet('tab', 'performa')
        ->call('setPerforma', 'klasifikasi')
        ->assertSee('Cuci')
        ->assertSee('Pemasangan');
});
