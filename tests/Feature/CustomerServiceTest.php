<?php

use App\Enums\CustomerArea;
use App\Enums\CustomerJenis;
use App\Enums\CustomerStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\UnitType;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Services\CustomerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mkUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };

    $this->admin = ($this->mkUser)(RoleName::Admin->value);
});

it('create membuat customer baru dgn status Lead', function () {
    $customer = app(CustomerService::class)->create([
        'nama' => 'Budi Santoso',
        'no_hp' => '628123456789',
        'jenis' => CustomerJenis::Perorangan->value,
        'area' => CustomerArea::Makassar->value,
        'sumber_lead' => LeadSource::Whatsapp->value,
        'email' => 'budi@test.com',
    ], $this->admin);

    expect($customer->nama)->toBe('Budi Santoso')
        ->and($customer->no_hp)->toBe('628123456789')
        ->and($customer->jenis)->toBe(CustomerJenis::Perorangan)
        ->and($customer->area)->toBe(CustomerArea::Makassar)
        ->and($customer->sumber_lead)->toBe(LeadSource::Whatsapp)
        ->and($customer->email)->toBe('budi@test.com')
        ->and($customer->status)->toBe(CustomerStatus::Lead);
});

it('create dgn alamat_pengerjaan membuat CustomerAddress utama', function () {
    $customer = app(CustomerService::class)->create([
        'nama' => 'Budi',
        'no_hp' => '628111',
        'alamat_pengerjaan' => 'Jl. Mawar No. 4',
    ], $this->admin);

    $alamat = $customer->addresses()->first();
    expect($alamat)->not->toBeNull()
        ->and($alamat->alamat)->toBe('Jl. Mawar No. 4')
        ->and($alamat->is_utama)->toBeTrue()
        ->and($customer->alamatUtama()->id)->toBe($alamat->id);
});

it('create tanpa alamat_pengerjaan TIDAK membuat CustomerAddress', function () {
    $customer = app(CustomerService::class)->create([
        'nama' => 'Budi',
        'no_hp' => '628111',
    ], $this->admin);

    expect(CustomerAddress::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
});

it('create dgn kode_ruangan (& alamat) membuat CustomerAcUnit dgn kode_unit auto-generate', function () {
    $customer = app(CustomerService::class)->create([
        'nama' => 'Budi',
        'no_hp' => '628111',
        'alamat_pengerjaan' => 'Jl. Mawar No. 4',
        'kode_ruangan' => 'Ruang Tamu',
        'jenis_unit' => UnitType::Split->value,
        'pk' => '1 PK',
    ], $this->admin);

    $unit = CustomerAcUnit::query()->where('customer_id', $customer->id)->first();
    expect($unit)->not->toBeNull()
        ->and($unit->kode_unit)->toBe('AC-01')
        ->and($unit->kode_ruangan)->toBe('Ruang Tamu')
        ->and($unit->jenis_unit)->toBe(UnitType::Split)
        ->and($unit->pk)->toBe('1 PK')
        ->and($unit->customer_address_id)->toBe($customer->alamatUtama()->id);
});

it('create dgn kode_ruangan TAPI TANPA alamat_pengerjaan tidak membuat unit (tidak ada alamat utk ditautkan)', function () {
    $customer = app(CustomerService::class)->create([
        'nama' => 'Budi',
        'no_hp' => '628111',
        'kode_ruangan' => 'Ruang Tamu',
    ], $this->admin);

    expect(CustomerAcUnit::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
});

it('create menolak nama atau no_hp kosong', function () {
    expect(fn () => app(CustomerService::class)->create(['no_hp' => '628111'], $this->admin))
        ->toThrow(BusinessRuleException::class, 'Nama pelanggan wajib');

    expect(fn () => app(CustomerService::class)->create(['nama' => 'Budi'], $this->admin))
        ->toThrow(BusinessRuleException::class, 'No. HP pelanggan wajib');
});

it('create hanya boleh Admin/Owner', function (string $role) {
    $user = ($this->mkUser)($role);

    app(CustomerService::class)->create(['nama' => 'Budi', 'no_hp' => '628111'], $user);
})->with(['finance' => RoleName::Finance->value, 'teknisi' => RoleName::Teknisi->value])
    ->throws(AuthorizationException::class);

it('cariByNoHp menemukan customer dgn no_hp persis sama, null kalau tidak ada', function () {
    Customer::factory()->create(['no_hp' => '628199998888']);

    $ketemu = app(CustomerService::class)->cariByNoHp('628199998888');
    $tidakKetemu = app(CustomerService::class)->cariByNoHp('628100001111');

    expect($ketemu)->not->toBeNull()
        ->and($ketemu->no_hp)->toBe('628199998888')
        ->and($tidakKetemu)->toBeNull()
        ->and(app(CustomerService::class)->cariByNoHp(''))->toBeNull();
});
