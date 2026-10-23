<?php

use App\Enums\OrderStatus;
use App\Filament\Pages\Akuntan;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\ServiceCatalog;
use App\Models\TeknisiExpense;
use App\Models\User;
use App\Services\AkuntanService;
use App\Services\LaporanRekapExcelService;
use App\Services\LaporanRekapPdfService;
use App\Services\LaporanRekapService;
use App\Support\Terbilang;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function rkUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function rkOrder(string $tanggal, int $harga): Order
{
    $order = Order::factory()->create([
        'customer_id' => Customer::factory()->create()->id,
        'service_catalog_id' => ServiceCatalog::factory()->create(['harga' => $harga])->id,
        'status' => OrderStatus::Selesai,
        'tanggal_jadwal' => $tanggal,
    ]);
    Payment::factory()->lunas()->create(['order_id' => $order->id, 'total_tagihan' => $harga, 'tanggal_bayar' => $tanggal]);

    return $order;
}

function rkSiapkanData(): void
{
    rkOrder('2026-10-02', 300000);
    rkOrder('2026-10-03', 150000);

    Expense::factory()->create(['tanggal' => '2026-10-02', 'nominal' => 50000, 'kategori' => 'material', 'keterangan' => 'Freon']);
    Expense::factory()->create(['tanggal' => '2026-10-02', 'nominal' => 20000, 'kategori' => 'perawatan', 'keterangan' => 'Oli']);
    Expense::factory()->create(['tanggal' => '2026-10-03', 'nominal' => 10000, 'kategori' => 'operasional', 'keterangan' => 'Parkir']);

    $teknisi = rkUser('teknisi');
    TeknisiExpense::create(['teknisi_id' => $teknisi->id, 'tanggal_input' => '2026-10-03', 'kategori' => 'bensin', 'nominal' => 25000, 'status' => 'approved']);
    TeknisiExpense::create(['teknisi_id' => $teknisi->id, 'tanggal_input' => '2026-10-03', 'kategori' => 'bensin', 'nominal' => 99000, 'status' => 'pending']);

    PayrollRun::create([
        'user_id' => $teknisi->id, 'periode_mulai' => '2026-10-01', 'periode_selesai' => '2026-10-31',
        'gaji_bersih' => 1000000, 'status' => 'dibayar', 'dibayar_pada' => '2026-10-05 10:00:00',
    ]);
}

it('terbilang mengubah angka ke kata rupiah', function (float|int $angka, string $kata) {
    expect(Terbilang::rupiah($angka))->toBe($kata);
})->with([
    [0, 'Nol Rupiah'],
    [11, 'Sebelas Rupiah'],
    [100, 'Seratus Rupiah'],
    [1000, 'Seribu Rupiah'],
    [2500, 'Dua Ribu Lima Ratus Rupiah'],
    [1_250_000, 'Satu Juta Dua Ratus Lima Puluh Ribu Rupiah'],
]);

it('susun: total rekap cocok dgn AkuntanService dan gaji ikut pengeluaran', function () {
    rkSiapkanData();
    $bulan = CarbonImmutable::parse('2026-10-01');

    $data = app(LaporanRekapService::class)->susun($bulan);
    $akuntan = app(AkuntanService::class);
    $awal = $bulan->startOfMonth();
    $akhir = $bulan->endOfMonth();

    $t = $data['rekap']['total'];
    expect($data['tanggal'])->toHaveCount(31)
        ->and($t['pendapatan'])->toBe($akuntan->totalPendapatan($akuntan->pendapatan($awal, $akhir)))
        ->and($t['material'])->toBe(50000.0)
        ->and($t['perawatan'])->toBe(20000.0)
        ->and($t['operasional'])->toBe(35000.0) // parkir + bensin approved; pending tidak ikut
        ->and($t['gaji'])->toBe(1000000.0)
        ->and($t['pengeluaran'])->toBe($akuntan->totalPengeluaran($akuntan->pengeluaran($awal, $akhir)) + 1000000.0)
        ->and($data['rekap']['laba'])->toBe(450000.0 - 1105000.0);
});

it('excel berisi 6 sheet dgn formula rekap', function () {
    rkSiapkanData();
    $data = app(LaporanRekapService::class)->susun(CarbonImmutable::parse('2026-10-01'));

    $isi = app(LaporanRekapExcelService::class)->render($data, 'PT. Uji');
    $tmp = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    file_put_contents($tmp, $isi);
    $book = IOFactory::load($tmp);
    @unlink($tmp);

    expect($book->getSheetNames())->toBe(['Operasional', 'Material', 'Perawatan', 'Omset Jasa', 'Omset Material', 'REKAP DANA']);

    $rekap = $book->getSheetByName('REKAP DANA');
    expect($rekap->getCell('C11')->getValue())->toStartWith('=SUMIF(')
        ->and($rekap->getCell('B42')->getValue())->toBe('TOTAL')
        ->and($book->getSheetByName('Material')->getCell('C11')->getCalculatedValue())->not->toBeNull();

    // Hitung ulang di Excel-engine: total pendapatan harus sama dgn data.
    expect((float) $rekap->getCell('K42')->getCalculatedValue())->toBe($data['rekap']['total']['pendapatan'])
        ->and((float) $rekap->getCell('H42')->getCalculatedValue())->toBe($data['rekap']['total']['pengeluaran']);
});

it('pdf berhasil dirender', function () {
    rkSiapkanData();
    $data = app(LaporanRekapService::class)->susun(CarbonImmutable::parse('2026-10-01'));

    $pdf = app(LaporanRekapPdfService::class)->render($data, 'PT. Uji');

    expect($pdf)->toStartWith('%PDF');
});

it('tombol unduh hanya untuk owner/admin/finance', function (string $role, bool $boleh) {
    rkSiapkanData();
    $user = rkUser($role);
    $this->actingAs($user);

    if (! $boleh) {
        $this->get(Akuntan::getUrl())->assertForbidden();

        return;
    }

    $this->get(Akuntan::getUrl())->assertOk()->assertSee('Unduh Excel')->assertSee('Unduh PDF');

    Livewire::test(Akuntan::class, ['bulan' => '2026-10'])->call('unduhExcel')->assertFileDownloaded('Rekap Bulanan Dana 2026-10.xlsx');
    Livewire::test(Akuntan::class, ['bulan' => '2026-10'])->call('unduhPdf')->assertFileDownloaded('Rekap Bulanan Dana 2026-10.pdf');
})->with([
    ['owner', true],
    ['admin', true],
    ['finance', true],
    ['hr', false],
]);
