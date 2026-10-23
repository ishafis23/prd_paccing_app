<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Filament\Pages\Akuntan;
use App\Filament\Pages\PengeluaranTeknisi;
use App\Livewire\Admin\ManajemenPengeluaranTeknisi;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ServiceCatalog;
use App\Models\TeknisiExpense;
use App\Models\User;
use App\Services\AkuntanService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function akUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * Order selesai dgn harga layanan utama tertentu, dikerjakan & lunas pada
 * tanggal tsb — tanggal_jadwal adalah acuan "tanggal pendapatan" (OmsetService).
 */
function akOrderLunas(string $tanggal, int $harga, string $nama = 'Budi'): Order
{
    $order = Order::factory()->create([
        'customer_id' => Customer::factory()->create(['nama' => $nama])->id,
        'service_catalog_id' => ServiceCatalog::factory()->create(['harga' => $harga])->id,
        'status' => OrderStatus::Selesai,
        'tanggal_jadwal' => $tanggal,
    ]);

    Payment::factory()->lunas()->create([
        'order_id' => $order->id,
        'total_tagihan' => $harga,
        'tanggal_bayar' => $tanggal,
    ]);

    return $order;
}

it('hanya owner/admin/finance yang boleh akses', function (string $role, bool $boleh) {
    $this->actingAs(akUser($role))->get(Akuntan::getUrl())
        ->{$boleh ? 'assertOk' : 'assertForbidden'}();
})->with([
    ['owner', true],
    ['admin', true],
    ['finance', true],
    ['hr', false],
    ['teknisi', false],
]);

it('halaman Pengeluaran Teknisi: owner/admin/finance boleh ACC, lain tidak', function (string $role, bool $boleh) {
    $this->actingAs(akUser($role))->get(PengeluaranTeknisi::getUrl())
        ->{$boleh ? 'assertOk' : 'assertForbidden'}();
})->with([
    ['owner', true],
    ['admin', true],
    ['finance', true],
    ['hr', false],
    ['teknisi', false],
]);

it('halaman Akuntan menampilkan tombol menuju ACC Pengeluaran Teknisi', function () {
    $this->actingAs(akUser(RoleName::Admin->value))->get(Akuntan::getUrl())
        ->assertOk()
        ->assertSee('ACC Pengeluaran Teknisi')
        ->assertSee('/admin/pengeluaran-teknisi', false);
});

it('pendapatan menghitung order selesai termasuk item tambahan, per tanggal jadwal (dikerjakan)', function () {
    $order = akOrderLunas('2026-10-02', 100000);
    OrderItem::factory()->create(['order_id' => $order->id, 'harga' => 50000, 'jumlah' => 2, 'nama_layanan' => 'Ganti Kapasitor']);

    akOrderLunas('2026-10-03', 75000);
    akOrderLunas('2026-09-30', 999000); // bulan lain

    Order::factory()->create(['status' => OrderStatus::Dikerjakan]); // belum selesai

    $rows = app(AkuntanService::class)->pendapatan(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'));

    expect($rows)->toHaveCount(2)
        ->and($rows->first()['tanggal'])->toBe('2026-10-03') // desc
        ->and($rows->firstWhere('order_id', $order->id)['total'])->toBe(200000.0)
        ->and($rows->firstWhere('order_id', $order->id)['items'])->toHaveCount(2)
        ->and($rows->firstWhere('order_id', $order->id)['items'][1]['tambahan'])->toBeTrue();

    $rekap = app(AkuntanService::class)->rekapHarian($rows);
    expect($rekap->pluck('total', 'tanggal')->all())->toBe(['2026-10-03' => 75000.0, '2026-10-02' => 200000.0]);
});

it('pengeluaran: admin + teknisi approved dihitung, pending terpisah, rejected diabaikan', function () {
    $teknisi = akUser('teknisi');
    Expense::factory()->create(['tanggal' => '2026-10-02', 'nominal' => 100000]);
    $buat = fn (string $status, int $nominal) => TeknisiExpense::create([
        'teknisi_id' => $teknisi->id, 'tanggal_input' => '2026-10-02', 'kategori' => 'bensin',
        'nominal' => $nominal, 'status' => $status,
    ]);
    $buat('approved', 20000);
    $buat('pending', 30000);
    $buat('rejected', 40000);
    Expense::factory()->create(['tanggal' => '2026-11-01', 'nominal' => 5000]);

    $service = app(AkuntanService::class);
    $rows = $service->pengeluaran(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'));

    expect($rows)->toHaveCount(3)
        ->and($service->totalPengeluaran($rows))->toBe(120000.0);

    $rekap = $service->rekapHarian($rows)->first();
    expect($rekap['total'])->toBe(120000.0)->and($rekap['pending'])->toBe(30000.0);
});

it('kartu hari ini tidak ikut filter bulan, kartu bulan ikut', function () {
    akOrderLunas(now()->toDateString(), 100000);
    akOrderLunas(now()->subMonthNoOverflow()->toDateString(), 50000);

    $service = app(AkuntanService::class);

    $ini = $service->kartu(CarbonImmutable::now());
    expect($ini['pendapatan_hari'])->toBe(100000.0)->and($ini['pendapatan_bulan'])->toBe(100000.0);

    $lalu = $service->kartu(CarbonImmutable::now()->subMonthNoOverflow());
    expect($lalu['pendapatan_hari'])->toBe(100000.0)->and($lalu['pendapatan_bulan'])->toBe(50000.0);
});

it('halaman menampilkan data dan modal detail', function () {
    $admin = akUser(RoleName::Admin->value);
    $hari = now()->toDateString();
    akOrderLunas($hari, 100000, 'Budi Akuntan');

    Livewire::actingAs($admin)->test(Akuntan::class)
        ->assertSee('Rp 100.000')
        ->call('bukaDetail', $hari)
        ->assertSee('Budi Akuntan')
        ->call('setTab', 'semua')
        ->assertSet('detailTanggal', null)
        ->assertSee('Budi Akuntan')
        ->call('setSemua', 'pengeluaran')
        ->assertSee('Belum ada data');
});


function akExpense(string $status = 'pending', int $nominal = 20000): TeknisiExpense
{
    return TeknisiExpense::create([
        'teknisi_id' => akUser('teknisi')->id, 'tanggal_input' => today(), 'kategori' => 'bensin',
        'nominal' => $nominal, 'status' => $status,
    ]);
}

it('ACC: pending disetujui langsung tanpa modal', function () {
    $e = akExpense();
    $admin = akUser('admin');

    Livewire::actingAs($admin)->test(ManajemenPengeluaranTeknisi::class)
        ->call('quickApprove', $e->id);

    expect($e->fresh())->status->toBe('approved')->approved_by->toBe($admin->id);
});

it('ACC: tolak lewat modal wajib alasan, lalu bisa diganti jadi setuju', function () {
    $e = akExpense();
    $lw = Livewire::actingAs(akUser('admin'))->test(ManajemenPengeluaranTeknisi::class)
        ->call('openApproval', $e->id)
        ->call('submitApproval')
        ->assertHasErrors('approvalCatatan')
        ->set('approvalCatatan', 'Nota tidak jelas')
        ->call('submitApproval')
        ->assertHasNoErrors();

    expect($e->fresh())->status->toBe('rejected')->catatan_approval->toBe('Nota tidak jelas');

    $lw->call('quickApprove', $e->id);

    expect($e->fresh())->status->toBe('approved')->catatan_approval->toBeNull();
});

it('ACC: yang sudah disetujui bisa ditolak (koreksi), tapi tidak bisa disetujui dua kali', function () {
    $e = akExpense('approved');
    $lw = Livewire::actingAs(akUser('admin'))->test(ManajemenPengeluaranTeknisi::class)
        ->call('openApproval', $e->id)
        ->set('approvalCatatan', 'Salah input nominal')
        ->call('submitApproval');

    expect($e->fresh()->status)->toBe('rejected');

    $lw->call('openApproval', $e->id)->assertSet('selectedExpense', null);
});

it('ACC: status yang berubah saat modal terbuka tidak menimpa keputusan lain', function () {
    $e = akExpense();
    $lw = Livewire::actingAs(akUser('admin'))->test(ManajemenPengeluaranTeknisi::class)
        ->call('openApproval', $e->id)
        ->set('approvalCatatan', 'Tidak sesuai');

    $e->update(['status' => 'rejected']);
    $lw->call('submitApproval')->assertSet('selectedExpense', null);

    expect($e->fresh())->status->toBe('rejected')->catatan_approval->toBeNull();
});

it('ACC: quickApprove menolak nominal di bawah batas', function (int $nominal) {
    $e = akExpense('pending', $nominal);

    Livewire::actingAs(akUser('admin'))->test(ManajemenPengeluaranTeknisi::class)
        ->call('quickApprove', $e->id);

    expect($e->fresh()->status)->toBe('pending');
})->with([500]);

it('ACC: quickApprove menyetujui nominal besar tanpa batas 5 juta', function () {
    $e = akExpense('pending', 6000000);

    Livewire::actingAs(akUser('admin'))->test(ManajemenPengeluaranTeknisi::class)
        ->call('quickApprove', $e->id);

    expect($e->fresh()->status)->toBe('approved');
});

it('ACC: quickApprove menyetujui pending bernominal valid', function () {
    $e = akExpense();

    Livewire::actingAs(akUser('finance'))->test(ManajemenPengeluaranTeknisi::class)
        ->call('quickApprove', $e->id);

    expect($e->fresh()->status)->toBe('approved');
});

it('ACC: role tanpa wewenang ditolak di semua aksi', function (string $role) {
    $e = akExpense();

    Livewire::actingAs(akUser($role))->test(ManajemenPengeluaranTeknisi::class)
        ->call('quickApprove', $e->id)
        ->assertForbidden();

    Livewire::actingAs(akUser($role))->test(ManajemenPengeluaranTeknisi::class)
        ->call('openApproval', $e->id)
        ->assertForbidden();

    expect($e->fresh()->status)->toBe('pending');
})->with(['teknisi', 'hr']);


it('ACC: halaman menampilkan daftar teknisi di filter & nama pelapor', function () {
    $e = akExpense();

    Livewire::actingAs(akUser('admin'))->test(ManajemenPengeluaranTeknisi::class)
        ->assertSee($e->teknisi->name);
});

it('Tambah Manual: admin mencatat pengeluaran atas nama teknisi, langsung approved', function () {
    $teknisi = akUser('teknisi');
    $admin = akUser('admin');

    Livewire::actingAs($admin)->test(ManajemenPengeluaranTeknisi::class)
        ->call('bukaTambah')
        ->assertSet('showTambah', true)
        ->set('formTeknisiId', $teknisi->id)
        ->set('formTanggal', today()->format('Y-m-d'))
        ->set('formKategori', 'bensin')
        ->set('formQty', 2)
        ->set('formHarga', 15000)
        ->assertSet('formNominal', 30000)
        ->set('formKeterangan', 'Isi bensin motor dinas')
        ->call('simpanManual')
        ->assertHasNoErrors()
        ->assertSet('showTambah', false);

    $expense = TeknisiExpense::where('teknisi_id', $teknisi->id)->first();
    expect($expense)->not->toBeNull()
        ->and($expense->status)->toBe('approved')
        ->and($expense->approved_by)->toBe($admin->id)
        ->and($expense->nominal)->toBe(30000)
        ->and($expense->kategori)->toBe('bensin');
});

it('Tambah Manual: nominal di atas 5 juta tetap bisa dicatat', function () {
    $teknisi = akUser('teknisi');

    Livewire::actingAs(akUser('admin'))->test(ManajemenPengeluaranTeknisi::class)
        ->call('bukaTambah')
        ->set('formTeknisiId', $teknisi->id)
        ->set('formTanggal', today()->format('Y-m-d'))
        ->set('formKategori', 'material')
        ->set('formQty', 1)
        ->set('formHarga', 6000000)
        ->assertSet('formNominal', 6000000)
        ->call('simpanManual')
        ->assertHasNoErrors();

    expect(TeknisiExpense::where('teknisi_id', $teknisi->id)->value('nominal'))->toBe(6000000);
});

it('Tambah Manual: wajib pilih teknisi & kategori valid', function () {
    Livewire::actingAs(akUser('finance'))->test(ManajemenPengeluaranTeknisi::class)
        ->call('bukaTambah')
        ->set('formTanggal', today()->format('Y-m-d'))
        ->set('formQty', 1)
        ->set('formHarga', 10000)
        ->call('simpanManual')
        ->assertHasErrors(['formTeknisiId', 'formKategori']);

    expect(TeknisiExpense::count())->toBe(0);
});

it('Tambah Manual: teknisi/hr tidak boleh mencatat pengeluaran manual', function (string $role) {
    Livewire::actingAs(akUser($role))->test(ManajemenPengeluaranTeknisi::class)
        ->call('bukaTambah')
        ->assertForbidden();

    expect(TeknisiExpense::count())->toBe(0);
})->with(['teknisi', 'hr']);

it('ACC: tab Rekap Kategori menjumlah menunggu+disetujui tanpa yang ditolak, filter lewat modal', function () {
    akExpense('pending', 20000);
    akExpense('approved', 10000);
    akExpense('rejected', 99000);

    $lw = Livewire::actingAs(akUser('admin'))->test(ManajemenPengeluaranTeknisi::class)
        ->assertSet('tampilan', 'daftar')
        ->set('tampilan', 'rekap')
        ->assertSee('Bensin')
        ->assertSee('Rp 30.000')
        ->assertDontSee('Rp 129.000');

    $lw->call('$set', 'tampilFilter', true)
        ->assertSee('Filter')
        ->set('filterKategori', 'makan')
        ->assertSet('filterKategori', 'makan')
        ->call('resetFilter')
        ->assertSet('filterKategori', '');
});
