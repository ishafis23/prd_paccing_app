<?php

use App\Enums\CustomerJenis;
use App\Enums\RoleName;
use App\Filament\Resources\CustomerResource\RelationManagers\AcUnitsRelationManager;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\User;
use App\Services\CustomerAcUnitImportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function cauAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);

    return $user;
}

/**
 * @param  array<int, array<int, mixed>>  $baris
 */
function cauBuatFile(array $baris, array $header = CustomerAcUnitImportService::HEADER): string
{
    $ss = new Spreadsheet;
    $ws = $ss->getActiveSheet();
    $ws->fromArray([$header], null, 'A1');
    $ws->fromArray($baris, null, 'A2');

    $path = tempnam(sys_get_temp_dir(), 'cau_test_').'.xlsx';
    (new Xlsx($ss))->save($path);

    return $path;
}

it('relation manager Unit AC hanya tampil utk customer jenis company', function () {
    $company = Customer::factory()->create(['jenis' => CustomerJenis::Company]);
    $perorangan = Customer::factory()->create(['jenis' => CustomerJenis::Perorangan]);

    expect(AcUnitsRelationManager::canViewForRecord($company, ''))->toBeTrue();
    expect(AcUnitsRelationManager::canViewForRecord($perorangan, ''))->toBeFalse();
});

it('bukan admin ditolak melakukan import unit ac', function () {
    $bukanAdmin = User::factory()->create();
    $bukanAdmin->assignRole(RoleName::Finance->value);
    $customer = Customer::factory()->create(['jenis' => CustomerJenis::Company]);

    $path = cauBuatFile([['AC-001', 'Kelas 1', '', '', '']]);

    expect(fn () => app(CustomerAcUnitImportService::class)->import($path, $customer, $bukanAdmin))
        ->toThrow(AuthorizationException::class);
});

it('import baris valid membuat unit ac terhubung ke customer', function () {
    $admin = cauAdmin();
    $customer = Customer::factory()->create(['jenis' => CustomerJenis::Company]);

    $path = cauBuatFile([
        ['AC-001', 'Kelas 3A', 'split', '1 PK', 'lantai 2'],
        ['AC-002', 'Ruang Guru', 'cassette', '2 PK', ''],
    ]);

    $hasil = app(CustomerAcUnitImportService::class)->import($path, $customer, $admin);

    expect($hasil['berhasil'])->toBe(2);
    expect($hasil['gagal'])->toBe(0);
    expect($customer->acUnits()->count())->toBe(2);

    $unit = CustomerAcUnit::where('kode_unit', 'AC-001')->first();
    expect($unit->kode_ruangan)->toBe('Kelas 3A');
    expect($unit->jenis_unit->value)->toBe('split');
    expect($unit->pk)->toBe('1 PK');
    expect($unit->customer_id)->toBe($customer->id);
});

it('kode_unit duplikat utk customer yg sama dilewati, tidak ganggu customer lain', function () {
    $admin = cauAdmin();
    $customerA = Customer::factory()->create(['jenis' => CustomerJenis::Company]);
    $customerB = Customer::factory()->create(['jenis' => CustomerJenis::Company]);

    CustomerAcUnit::factory()->create(['customer_id' => $customerA->id, 'kode_unit' => 'AC-001']);

    // Sama kode_unit tapi customer beda -> boleh, bukan duplikat global.
    $pathB = cauBuatFile([['AC-001', 'Lobby', '', '', '']]);
    $hasilB = app(CustomerAcUnitImportService::class)->import($pathB, $customerB, $admin);
    expect($hasilB['berhasil'])->toBe(1);

    // Sama kode_unit & customer sama -> dilewati.
    $pathA = cauBuatFile([
        ['AC-001', 'Kelas Baru', '', '', ''],
        ['AC-001', 'Duplikat Dalam File', '', '', ''],
    ]);
    $hasilA = app(CustomerAcUnitImportService::class)->import($pathA, $customerA, $admin);
    expect($hasilA['berhasil'])->toBe(0);
    expect($hasilA['dilewati'])->toBe(2);
    expect($customerA->acUnits()->count())->toBe(1);
});

it('baris tanpa kode_unit/kode_ruangan atau jenis_unit tidak valid dilaporkan gagal', function () {
    $admin = cauAdmin();
    $customer = Customer::factory()->create(['jenis' => CustomerJenis::Company]);

    $path = cauBuatFile([
        ['', 'Kelas 1', '', '', ''],
        ['AC-010', '', '', '', ''],
        ['AC-011', 'Kelas 2', 'planet_mars', '', ''],
    ]);

    $hasil = app(CustomerAcUnitImportService::class)->import($path, $customer, $admin);

    expect($hasil['berhasil'])->toBe(0);
    expect($hasil['gagal'])->toBe(3);
});

it('menolak file lebih dari MAX_BARIS baris', function () {
    $admin = cauAdmin();
    $customer = Customer::factory()->create(['jenis' => CustomerJenis::Company]);

    $baris = collect(range(1, CustomerAcUnitImportService::MAX_BARIS + 1))
        ->map(fn ($i) => ["AC-{$i}", "Ruangan {$i}", '', '', ''])
        ->all();

    $path = cauBuatFile($baris);

    expect(fn () => app(CustomerAcUnitImportService::class)->import($path, $customer, $admin))
        ->toThrow(\App\Exceptions\BusinessRuleException::class);
});

it('unduh template unit ac menghasilkan streamed response', function () {
    $response = app(CustomerAcUnitImportService::class)->unduhTemplate();

    expect($response)->toBeInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class);
});

it('halaman edit customer company memuat tanpa error (relation manager Unit AC lazy-load via JS, dicek terpisah)', function () {
    $admin = cauAdmin();
    $customer = Customer::factory()->create(['jenis' => CustomerJenis::Company]);

    // Filament me-lazy-load isi relation manager via x-intersect (JS) —
    // "Unit AC" TIDAK ada di HTML awal walau tab-nya valid. Ini cuma
    // memastikan halaman edit tetap render normal (tidak fatal error)
    // dgn RelationManager terpasang; visibilitas tab dicek via
    // canViewForRecord (test terpisah) & isi tabel via Livewire::test.
    $this->actingAs($admin)->get("/admin/customers/{$customer->id}/edit")
        ->assertOk();
});

it('livewire component Unit AC menampilkan data & aksi import utk customer company', function () {
    $admin = cauAdmin();
    $this->actingAs($admin);

    $customer = Customer::factory()->create(['jenis' => CustomerJenis::Company]);
    CustomerAcUnit::factory()->create(['customer_id' => $customer->id, 'kode_unit' => 'AC-001', 'kode_ruangan' => 'Kelas 3A']);

    Livewire::test(AcUnitsRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => \App\Filament\Resources\CustomerResource\Pages\EditCustomer::class,
    ])
        ->assertSee('AC-001')
        ->assertSee('Kelas 3A')
        ->assertTableActionExists('importUnitAc')
        ->assertTableActionExists('unduhTemplateUnitAc');
});
