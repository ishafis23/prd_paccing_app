<?php

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerImportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function ciAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);

    return $user;
}

/**
 * @param  array<int, array<int, mixed>>  $baris
 */
function ciBuatFile(array $baris, array $header = CustomerImportService::HEADER): string
{
    $ss = new Spreadsheet;
    $ws = $ss->getActiveSheet();
    $ws->fromArray([$header], null, 'A1');
    $ws->fromArray($baris, null, 'A2');

    $path = tempnam(sys_get_temp_dir(), 'ci_test_').'.xlsx';
    (new Xlsx($ss))->save($path);

    return $path;
}

it('bukan admin ditolak melakukan import', function () {
    $bukanAdmin = User::factory()->create();
    $bukanAdmin->assignRole(RoleName::Finance->value);

    $path = ciBuatFile([['Budi', '', '081234567890', '', '', '', '', '', '']]);

    expect(fn () => app(CustomerImportService::class)->import($path, $bukanAdmin))
        ->toThrow(AuthorizationException::class);
});

it('import baris valid membuat customer dgn default jenis/area/sumber/status', function () {
    $admin = ciAdmin();

    $path = ciBuatFile([
        ['PT Kalla Toyota', 'company', '081234567890', 'budi@example.com', 'Jl Mawar 4', 'gowa', 'referral', 'aktif', 'catatan a'],
        ['Sari Wulandari', '', '081298765432', '', '', '', '', '', ''],
    ]);

    $hasil = app(CustomerImportService::class)->import($path, $admin);

    expect($hasil['berhasil'])->toBe(2);
    expect($hasil['dilewati'])->toBe(0);
    expect($hasil['gagal'])->toBe(0);

    $budi = Customer::where('no_hp', '081234567890')->first();
    expect($budi->nama)->toBe('PT Kalla Toyota');
    expect($budi->jenis->value)->toBe('company');
    expect($budi->email)->toBe('budi@example.com');
    expect($budi->area->value)->toBe('gowa');
    expect($budi->status->value)->toBe('aktif');

    $sari = Customer::where('no_hp', '081298765432')->first();
    expect($sari->jenis->value)->toBe('perorangan'); // default
    expect($sari->area->value)->toBe('makassar'); // default
    expect($sari->sumber_lead->value)->toBe('whatsapp'); // default
    expect($sari->status->value)->toBe('lead'); // default
});

it('import Excel dgn kolom alamat terisi otomatis bikin customer_addresses (dev-plan/admin/03 B76 — bulk insert lewati Eloquent event)', function () {
    $admin = ciAdmin();

    $path = ciBuatFile([
        ['PT Kalla Toyota', 'company', '081234567890', '', 'Jl Mawar 4', 'gowa', '', '', ''],
        ['Tanpa Alamat', '', '081200000001', '', '', '', '', '', ''],
    ]);

    app(CustomerImportService::class)->import($path, $admin);

    $adaAlamat = Customer::where('no_hp', '081234567890')->firstOrFail();
    $tanpaAlamat = Customer::where('no_hp', '081200000001')->firstOrFail();

    expect($adaAlamat->addresses()->count())->toBe(1)
        ->and($adaAlamat->alamatUtama()->alamat)->toBe('Jl Mawar 4')
        ->and($tanpaAlamat->addresses()->count())->toBe(0);
});

it('no_hp duplikat (format beda 0812.../62812...) dilewati, data lama tidak berubah', function () {
    $admin = ciAdmin();
    Customer::factory()->create(['nama' => 'Lama', 'no_hp' => '081234567890']);

    $path = ciBuatFile([
        ['Baru Ganti Nama', '', '6281234567890', '', '', '', '', '', ''], // sama nomor, format beda
        ['Duplikat Dalam File', '', '081299999999', '', '', '', '', '', ''],
        ['Duplikat Dalam File', '', '081299999999', '', '', '', '', '', ''],
    ]);

    $hasil = app(CustomerImportService::class)->import($path, $admin);

    expect($hasil['berhasil'])->toBe(1);
    expect($hasil['dilewati'])->toBe(2);

    $existing = Customer::where('no_hp', '081234567890')->first();
    expect($existing->nama)->toBe('Lama'); // tidak berubah

    expect(Customer::where('no_hp', '081299999999')->count())->toBe(1);
});

it('baris tanpa nama/no_hp, jenis/enum tidak valid dilaporkan gagal & dilewati', function () {
    $admin = ciAdmin();

    $path = ciBuatFile([
        ['', '', '081234567890', '', '', '', '', '', ''], // nama kosong
        ['Tanpa HP', '', '', '', '', '', '', '', ''], // no_hp kosong
        ['Area Salah', '', '081200000001', '', '', 'planet_mars', '', '', ''], // area invalid
        ['Email Salah', '', '081200000002', 'bukan-email', '', '', '', '', ''],
        ['Jenis Salah', 'yayasan', '081200000003', '', '', '', '', '', ''], // jenis invalid
    ]);

    $hasil = app(CustomerImportService::class)->import($path, $admin);

    expect($hasil['berhasil'])->toBe(0);
    expect($hasil['gagal'])->toBe(5);
    expect($hasil['rincian'])->toHaveCount(5);
});

it('menolak file lebih dari MAX_BARIS baris', function () {
    $admin = ciAdmin();

    $baris = collect(range(1, CustomerImportService::MAX_BARIS + 1))
        ->map(fn ($i) => ["Customer {$i}", '', "0812000{$i}", '', '', '', '', '', ''])
        ->all();

    $path = ciBuatFile($baris);

    expect(fn () => app(CustomerImportService::class)->import($path, $admin))
        ->toThrow(BusinessRuleException::class);
});

it('import ribuan baris (skala nyata ~4000) selesai & tersimpan lengkap', function () {
    $admin = ciAdmin();

    $baris = collect(range(1, 4000))
        ->map(fn ($i) => ["Customer {$i}", '', sprintf('0812%08d', $i), '', '', '', '', '', ''])
        ->all();

    $path = ciBuatFile($baris);

    $mulai = microtime(true);
    $hasil = app(CustomerImportService::class)->import($path, $admin);
    $durasi = microtime(true) - $mulai;

    expect($hasil['berhasil'])->toBe(4000);
    expect($hasil['gagal'])->toBe(0);
    expect(Customer::count())->toBe(4000);
    expect($durasi)->toBeLessThan(30.0);
});

it('unduh template menghasilkan file xlsx dgn header yg benar', function () {
    $response = app(CustomerImportService::class)->unduhTemplate();

    expect($response)->toBeInstanceOf(StreamedResponse::class);
});
