<?php

use App\Enums\CustomerJenis;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use App\Filament\Resources\CustomerResource\RelationManagers\AcUnitsRelationManager;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\ServiceCatalog;
use App\Models\Team;
use App\Models\User;
use App\Services\OrderDispatchImportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mkAdmin = function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    };

    $this->mkTeknisi = function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Teknisi->value);

        return $user;
    };
});

/**
 * @param  array<int, array<int, mixed>>  $baris
 */
function odiBuatFile(array $baris, array $header = OrderDispatchImportService::HEADER): string
{
    $ss = new Spreadsheet;
    $ws = $ss->getActiveSheet();
    $ws->fromArray([$header], null, 'A1');
    $ws->fromArray($baris, null, 'A2');

    $path = tempnam(sys_get_temp_dir(), 'odi_test_').'.xlsx';
    (new Xlsx($ss))->save($path);

    return $path;
}

it('import membuat satu order dgn order_items sesuai baris valid, harga default & override', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create(['jenis' => CustomerJenis::Company]);
    $unit1 = CustomerAcUnit::factory()->create(['customer_id' => $customer->id, 'kode_unit' => 'AC-001']);
    $unit2 = CustomerAcUnit::factory()->create(['customer_id' => $customer->id, 'kode_unit' => 'AC-002']);
    $catalog = ServiceCatalog::factory()->create(['harga' => 100000]);

    $path = odiBuatFile([
        ['AC-001', '', ''],
        ['AC-002', '150000', 'Ekstra tinggi'],
    ]);

    $hasil = app(OrderDispatchImportService::class)->import($path, $customer, [
        'service_catalog_id' => $catalog->id,
        'tanggal_jadwal' => '2026-10-01',
    ], $admin);

    expect($hasil['berhasil'])->toBe(2);
    expect($hasil['gagal'])->toBe(0);
    expect($hasil['order'])->not->toBeNull();

    $order = $hasil['order'];
    expect($order->customer_id)->toBe($customer->id);
    expect($order->status)->toBe(OrderStatus::Baru);
    expect($order->orderItems)->toHaveCount(2);

    $item1 = $order->orderItems->firstWhere('customer_ac_unit_id', $unit1->id);
    $item2 = $order->orderItems->firstWhere('customer_ac_unit_id', $unit2->id);
    expect((float) $item1->harga)->toBe(100000.0); // pakai harga default dari katalog
    expect((float) $item2->harga)->toBe(150000.0); // override per baris
    expect($item2->catatan)->toBe('Ekstra tinggi');
});

it('import melewati kode_unit yang tidak terdaftar & duplikat dalam file', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create();
    CustomerAcUnit::factory()->create(['customer_id' => $customer->id, 'kode_unit' => 'AC-001']);
    $catalog = ServiceCatalog::factory()->create();

    $path = odiBuatFile([
        ['AC-001', '', ''],
        ['AC-999', '', ''], // tidak terdaftar
        ['AC-001', '', ''], // duplikat dalam file
    ]);

    $hasil = app(OrderDispatchImportService::class)->import($path, $customer, [
        'service_catalog_id' => $catalog->id,
    ], $admin);

    expect($hasil['berhasil'])->toBe(1);
    expect($hasil['gagal'])->toBe(2);
    expect($hasil['order']->orderItems)->toHaveCount(1);
});

it('import tanpa baris valid tidak membuat order sama sekali', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create();
    $catalog = ServiceCatalog::factory()->create();

    $path = odiBuatFile([
        ['AC-TIDAK-ADA', '', ''],
    ]);

    $hasil = app(OrderDispatchImportService::class)->import($path, $customer, [
        'service_catalog_id' => $catalog->id,
    ], $admin);

    expect($hasil['order'])->toBeNull();
    expect($hasil['berhasil'])->toBe(0);
    expect($hasil['gagal'])->toBe(1);
});

it('import menolak jenis layanan tidak dipilih, nonaktif, atau harga override tidak valid', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create();
    $catalogNonaktif = ServiceCatalog::factory()->create(['aktif' => false]);
    $path = odiBuatFile([['AC-001', '', '']]);

    expect(fn () => app(OrderDispatchImportService::class)->import($path, $customer, [], $admin))
        ->toThrow(BusinessRuleException::class, 'wajib dipilih');

    expect(fn () => app(OrderDispatchImportService::class)->import($path, $customer, ['service_catalog_id' => $catalogNonaktif->id], $admin))
        ->toThrow(BusinessRuleException::class, 'nonaktif');

    $catalog = ServiceCatalog::factory()->create();
    expect(fn () => app(OrderDispatchImportService::class)->import($path, $customer, ['service_catalog_id' => $catalog->id, 'harga' => -1], $admin))
        ->toThrow(BusinessRuleException::class, 'tidak valid');
});

it('import dgn team_id langsung meng-assign tim ke order', function () {
    $admin = ($this->mkAdmin)();
    $pic = ($this->mkTeknisi)();
    $anggota = ($this->mkTeknisi)();
    $team = Team::factory()->create(['pic_teknisi_id' => $pic->id]);
    $team->members()->sync([$pic->id, $anggota->id]);

    $customer = Customer::factory()->create();
    CustomerAcUnit::factory()->create(['customer_id' => $customer->id, 'kode_unit' => 'AC-001']);
    $catalog = ServiceCatalog::factory()->create();
    $path = odiBuatFile([['AC-001', '', '']]);

    $hasil = app(OrderDispatchImportService::class)->import($path, $customer, [
        'service_catalog_id' => $catalog->id,
        'team_id' => $team->id,
    ], $admin);

    $order = $hasil['order']->fresh();
    expect($order->teknisi_id)->toBe($pic->id);
    expect($order->status)->toBe(OrderStatus::Terjadwal);
    expect($order->team_id)->toBe($team->id);
});

it('import menolak bukan admin/owner', function () {
    $teknisi = ($this->mkTeknisi)();
    $customer = Customer::factory()->create();
    $catalog = ServiceCatalog::factory()->create();
    $path = odiBuatFile([['AC-001', '', '']]);

    app(OrderDispatchImportService::class)->import($path, $customer, ['service_catalog_id' => $catalog->id], $teknisi);
})->throws(AuthorizationException::class);

it('unduh template order massal menghasilkan streamed response', function () {
    $response = app(OrderDispatchImportService::class)->unduhTemplate();

    expect($response)->toBeInstanceOf(StreamedResponse::class);
});

it('livewire: header aksi Buat Order Massal & Unduh Template tersedia di relation manager Unit AC', function () {
    $admin = ($this->mkAdmin)();
    $this->actingAs($admin);
    $customer = Customer::factory()->create();

    Livewire::test(AcUnitsRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => EditCustomer::class,
    ])
        ->assertTableActionExists('importOrderMassal')
        ->assertTableActionExists('unduhTemplateOrderMassal');
});
