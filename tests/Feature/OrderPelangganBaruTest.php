<?php

use App\Enums\CustomerArea;
use App\Enums\CustomerJenis;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\UnitType;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\ServiceCatalog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);
});

it('mode Pelanggan Baru membuat Customer + Alamat + Unit AC + Order sekaligus (dev-plan/16)', function () {
    $catalog = ServiceCatalog::factory()->create(['aktif' => true]);

    Livewire::actingAs($this->admin)
        ->test(CreateOrder::class)
        ->fillForm([
            'mode_pelanggan' => 'baru',
            'pelanggan_baru_nama' => 'Andi Wijaya',
            'pelanggan_baru_no_hp' => '628123123123',
            'pelanggan_baru_jenis' => CustomerJenis::Company->value,
            'pelanggan_baru_area' => CustomerArea::Gowa->value,
            'pelanggan_baru_sumber_lead' => LeadSource::Instagram->value,
            'pelanggan_baru_email' => 'andi@test.com',
            'pelanggan_baru_kode_ruangan' => 'Ruang Server',
            'pelanggan_baru_jenis_unit' => UnitType::Cassette->value,
            'pelanggan_baru_pk' => '2 PK',
            'service_catalog_id' => $catalog->id,
            'jumlah_unit' => 1,
            'alamat_pengerjaan' => 'Jl. Perintis No. 10',
            'jenis_pelanggan' => CustomerJenis::Company->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $customer = Customer::where('no_hp', '628123123123')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->nama)->toBe('Andi Wijaya')
        ->and($customer->jenis)->toBe(CustomerJenis::Company)
        ->and($customer->area)->toBe(CustomerArea::Gowa)
        ->and($customer->sumber_lead)->toBe(LeadSource::Instagram)
        ->and($customer->email)->toBe('andi@test.com');

    $alamat = CustomerAddress::where('customer_id', $customer->id)->first();
    expect($alamat)->not->toBeNull()
        ->and($alamat->alamat)->toBe('Jl. Perintis No. 10')
        ->and($alamat->is_utama)->toBeTrue();

    $unit = CustomerAcUnit::where('customer_id', $customer->id)->first();
    expect($unit)->not->toBeNull()
        ->and($unit->kode_unit)->toBe('AC-01')
        ->and($unit->kode_ruangan)->toBe('Ruang Server')
        ->and($unit->customer_address_id)->toBe($alamat->id);

    $order = Order::where('customer_id', $customer->id)->first();
    expect($order)->not->toBeNull()
        ->and($order->customer_address_id)->toBe($alamat->id)
        ->and($order->customer_ac_unit_id)->toBe($unit->id)
        ->and($order->alamat_pengerjaan)->toBe('Jl. Perintis No. 10');
});

it('mode Pelanggan Baru tanpa kode_ruangan tetap membuat order, tanpa Unit AC', function () {
    $catalog = ServiceCatalog::factory()->create(['aktif' => true]);

    Livewire::actingAs($this->admin)
        ->test(CreateOrder::class)
        ->fillForm([
            'mode_pelanggan' => 'baru',
            'pelanggan_baru_nama' => 'Siti',
            'pelanggan_baru_no_hp' => '628199988877',
            'pelanggan_baru_area' => CustomerArea::Makassar->value,
            'service_catalog_id' => $catalog->id,
            'jumlah_unit' => 1,
            'alamat_pengerjaan' => 'Jl. Sudirman 5',
            'jenis_pelanggan' => CustomerJenis::Perorangan->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $customer = Customer::where('no_hp', '628199988877')->first();
    expect($customer)->not->toBeNull();
    expect(CustomerAcUnit::where('customer_id', $customer->id)->exists())->toBeFalse();

    $order = Order::where('customer_id', $customer->id)->first();
    expect($order->customer_ac_unit_id)->toBeNull();
});

it('mode Pelanggan Baru: No. HP sudah terdaftar tampil sbg warning, TIDAK memblokir submit', function () {
    $existing = Customer::factory()->create(['no_hp' => '628177776666', 'nama' => 'Rudi Lama']);
    $catalog = ServiceCatalog::factory()->create(['aktif' => true]);

    $component = Livewire::actingAs($this->admin)
        ->test(CreateOrder::class)
        ->fillForm([
            'mode_pelanggan' => 'baru',
            'pelanggan_baru_nama' => 'Rudi Baru',
            'pelanggan_baru_no_hp' => '628177776666',
            'pelanggan_baru_area' => CustomerArea::Makassar->value,
            'service_catalog_id' => $catalog->id,
            'jumlah_unit' => 1,
            'alamat_pengerjaan' => 'Jl. Baru 1',
            'jenis_pelanggan' => CustomerJenis::Perorangan->value,
        ]);

    $component->assertSee('Rudi Lama');

    $component->call('create')->assertHasNoFormErrors();

    // Tidak diblokir: customer baru dgn nomor sama tetap terbuat (warning, bukan guard keras).
    expect(Customer::where('no_hp', '628177776666')->count())->toBe(2);
});

it('mode Pelanggan Terdaftar (default) tetap seperti sebelumnya — tidak ada regresi', function () {
    $customer = Customer::factory()->create();
    $catalog = ServiceCatalog::factory()->create(['aktif' => true]);

    Livewire::actingAs($this->admin)
        ->test(CreateOrder::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'service_catalog_id' => $catalog->id,
            'jumlah_unit' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = Order::where('customer_id', $customer->id)->first();
    expect($order)->not->toBeNull();
});
