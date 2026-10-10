<?php

use App\Enums\OrderStatus;
use App\Enums\ServiceType;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\DashboardPimpinan;
use App\Filament\Widgets\OmsetJasaMaterialWidget;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\AkuntanService;
use App\Services\DashboardPimpinanService;
use App\Services\FinanceService;
use App\Services\OmsetService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(Database\Seeders\RolesAndPermissionsSeeder::class);
});

function omUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** Order selesai (Cuci AC) lunas pada tanggal tsb. */
function omOrder(string $tanggal, int $harga, int $unit = 1, array $extraItems = []): Order
{
    $order = Order::factory()->create([
        'service_catalog_id' => ServiceCatalog::factory()->create(['harga' => $harga, 'jenis_layanan' => ServiceType::CuciAc])->id,
        'status' => OrderStatus::Selesai,
        'jumlah_unit' => $unit,
    ]);
    $order->orderItems()->update(['jumlah' => $unit]);

    foreach ($extraItems as $item) {
        OrderItem::factory()->create(array_merge(['order_id' => $order->id], $item));
    }

    Payment::factory()->lunas()->create([
        'order_id' => $order->id,
        'total_tagihan' => $harga * $unit,
        'tanggal_bayar' => $tanggal,
    ]);

    return $order->refresh();
}

function omSkenario(): array
{
    // September: cuci 2 unit @100rb + kapasitor (material) 55rb + service 200rb (jasa)
    omOrder('2026-09-05', 100000, 2, [
        ['kategori' => null, 'nama_layanan' => 'Ganti Kapasitor', 'harga' => 55000, 'jumlah' => 1, 'komponen' => 'material'],
        ['kategori' => ServiceType::ServiceAc, 'nama_layanan' => 'Service AC', 'harga' => 200000, 'jumlah' => 1, 'komponen' => 'jasa'],
    ]);
    // Pengadaan (material) 3jt
    omOrder('2026-09-20', 3000000, 1, [])->orderItems->first()
        ->update(['kategori' => ServiceType::PengadaanAc, 'nama_layanan' => 'Pasang AC', 'komponen' => 'material']);
    // Oktober: di luar periode September
    omOrder('2026-10-02', 70000, 1);

    return [CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30')];
}

it('Σ jasa + Σ material = Σ total pada tabel omset, per kategori dan rinci', function () {
    [$dari, $sampai] = omSkenario();
    $omset = app(OmsetService::class);

    foreach (['kategori', 'transaksi'] as $mode) {
        $rows = $omset->ringkasan($dari, $sampai, $mode);
        $total = $omset->total($rows);

        expect($total['jasa'] + $total['material'])->toBe($total['total'])
            ->and($total['jasa'])->toBe(400000.0)
            ->and($total['material'])->toBe(3055000.0)
            ->and($total['total'])->toBe(3455000.0);

        foreach ($rows as $r) {
            expect($r['jasa'] + $r['material'])->toBe($r['total']);
        }
    }

    $rinci = collect($omset->ringkasan($dari, $sampai, 'transaksi'));
    expect($rinci->firstWhere('label', 'Ganti Kapasitor')['material'])->toBe(55000.0)
        ->and($rinci->firstWhere('label', 'Pasang AC')['total'])->toBe(3000000.0);
});

it('angka OmsetService = akuntan = pimpinan = finance untuk periode yang sama', function () {
    [$dari, $sampai] = omSkenario();
    $omset = app(OmsetService::class);
    $total = $omset->total($omset->ringkasan($dari, $sampai));

    $pendapatan = app(AkuntanService::class)->pendapatan($dari, $sampai);
    $labaRugi = app(FinanceService::class)->labaRugi($dari, $sampai);
    $pimpinan = app(DashboardPimpinanService::class);
    $keuangan = $pimpinan->keuangan($dari, $sampai);
    $klas = $pimpinan->klasifikasiPengerjaan($dari, $sampai);

    expect((float) $pendapatan->sum('total'))->toBe($total['total'])
        ->and((float) $pendapatan->sum('total_jasa'))->toBe($total['jasa'])
        ->and((float) $pendapatan->sum('total_material'))->toBe($total['material'])
        ->and($labaRugi['pendapatan'])->toBe($total['total'])
        ->and($labaRugi['pendapatan_jasa'])->toBe($total['jasa'])
        ->and($labaRugi['pendapatan_material'])->toBe($total['material'])
        ->and($keuangan['pendapatan'])->toBe($total['total'])
        // klasifikasi pimpinan (semua item ber-kategori + baris tanpa kategori di luar grup)
        ->and($klas['cuci']['rupiah'])->toBe(200000.0)
        ->and($klas['service']['rupiah'])->toBe(200000.0)
        ->and($klas['service']['jasa'])->toBe(200000.0)
        ->and($klas['pemasangan']['rupiah'])->toBe(3000000.0)
        ->and($klas['pemasangan']['material'])->toBe(3000000.0)
        ->and($klas['pemasangan']['items'][0])->toHaveKeys(['jasa', 'material']);
});

it('tanggal pendapatan satu aturan: klasifikasi pimpinan memakai tanggal bayar, bukan updated_at', function () {
    $order = omOrder('2026-08-15', 100000);
    // updated_at order dipindah ke September, tetapi dibayar Agustus
    Order::query()->whereKey($order->id)->update(['updated_at' => '2026-09-10 10:00:00']);

    $pimpinan = app(DashboardPimpinanService::class);
    $agustus = $pimpinan->klasifikasiPengerjaan(CarbonImmutable::parse('2026-08-01'), CarbonImmutable::parse('2026-08-31'));
    $september = $pimpinan->klasifikasiPengerjaan(CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30'));

    expect($agustus['cuci']['rupiah'])->toBe(100000.0)
        ->and($september['cuci']['rupiah'])->toBe(0.0);
});

it('filter bulan vs rentang tanggal mengembalikan angka yang benar', function () {
    omSkenario();
    $omset = app(OmsetService::class);
    $hitung = function (array $filters) use ($omset): float {
        [$dari, $sampai] = $omset->rentangDariFilter($filters);

        return $omset->total($omset->ringkasan($dari, $sampai))['total'];
    };

    expect($hitung(['mode' => 'bulan', 'bulan' => '2026-09']))->toBe(3455000.0)
        ->and($hitung(['mode' => 'bulan', 'bulan' => '2026-10']))->toBe(70000.0)
        ->and($hitung(['mode' => 'rentang', 'dari' => '2026-09-01', 'sampai' => '2026-09-10']))->toBe(455000.0)
        ->and($hitung(['mode' => 'rentang', 'dari' => '2026-09-20', 'sampai' => '2026-09-20']))->toBe(3000000.0)
        ->and($hitung(['mode' => 'rentang', 'dari' => '2026-09-10', 'sampai' => '2026-09-01']))->toBe(455000.0); // dari > sampai ditukar otomatis
});

it('dashboard (HTTP, owner) menampilkan kartu + tabel dengan angka sama dgn service, filter dari URL', function () {
    [$dari, $sampai] = omSkenario();
    $omset = app(OmsetService::class);
    $rows = $omset->ringkasan($dari, $sampai);
    $total = $omset->total($rows);
    $rp = fn (float $v): string => 'Rp'.number_format($v, 0, ',', '.');

    $html = $this->actingAs(omUser('owner'))
        ->get(Dashboard::getUrl(['filters' => ['mode' => 'bulan', 'bulan' => '2026-09']]))
        ->assertOk()
        ->assertSee('Cuci AC')
        ->assertSee('Service AC')
        ->assertSee('Pasang/Pengadaan')
        ->assertSee('Omset Jasa')
        ->assertSee('Omset Material')
        ->getContent();

    expect($total['total'])->toBe(3455000.0);
    // Kartu per kategori, tidak digabung: Cuci AC (2 unit) = 200rb + kapasitor? (kapasitor kategori null → Lainnya)
    foreach ($rows as $r) {
        expect($html)->toContain($rp($r['total']));
    }
    expect($html)->toContain($rp($total['total']))
        ->and($html)->toContain($rp($total['jasa']))
        ->and($html)->toContain($rp($total['material']));

    // Rentang 1–10 Sep hanya memuat order pertama (455rb), bukan pengadaan 3jt.
    $rentang = $this->get(Dashboard::getUrl(['filters' => ['mode' => 'rentang', 'dari' => '2026-09-01', 'sampai' => '2026-09-10']]))
        ->assertOk()
        ->getContent();
    expect($rentang)->toContain('Rp455.000')->and($rentang)->not->toContain('Rp3.455.000');
});

it('widget tabel: toggle rinci per transaksi dan ekspor CSV', function () {
    omSkenario();

    $widget = Livewire::actingAs(omUser('owner'))
        ->test(OmsetJasaMaterialWidget::class, ['filters' => ['mode' => 'bulan', 'bulan' => '2026-09']])
        ->assertSee('Rp3.455.000')
        ->assertDontSee('Ganti Kapasitor')
        ->call('toggleRinci')
        ->assertSee('Ganti Kapasitor')
        ->assertSee('Pasang AC');

    $widget->call('exportCsv')->assertFileDownloaded('omset-transaksi-2026-09-01_2026-09-30.csv');
});

it('preset cepat mengatur filter dashboard', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00')); // Rabu

    $page = Livewire::actingAs(omUser('owner'))->test(Dashboard::class);

    $page->call('terapkanPreset', 'hari_ini')->assertSet('filters.mode', 'rentang')->assertSet('filters.dari', '2026-09-16');
    $page->call('terapkanPreset', 'pekan_ini')->assertSet('filters.dari', '2026-09-14')->assertSet('filters.sampai', '2026-09-20');
    $page->call('terapkanPreset', 'bulan_lalu')->assertSet('filters.mode', 'bulan')->assertSet('filters.bulan', '2026-08');
    $page->call('terapkanPreset', 'bulan_ini')->assertSet('filters.bulan', '2026-09');
});

it('akses dashboard & dashboard pimpinan sesuai role', function (string $role, bool $pimpinanBoleh) {
    $user = omUser($role);

    $this->actingAs($user)->get(DashboardPimpinan::getUrl())
        ->{$pimpinanBoleh ? 'assertOk' : 'assertForbidden'}();

    // Widget omset hanya terlihat owner/admin/finance.
    auth()->login($user);
    expect(OmsetJasaMaterialWidget::canView())->toBe(in_array($role, ['owner', 'admin', 'finance'], true));
})->with([
    ['owner', true],
    ['admin', true],
    ['finance', false],
    ['hr', false],
    ['teknisi', false],
]);
