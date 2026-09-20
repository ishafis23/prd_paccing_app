<?php

use App\Enums\CustomerArea;
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

it('mode Pelanggan Baru membuat Customer + Alamat + Unit AC + Order sekaligus (dev-plan/18, wizard)', function () {
    $catalog = ServiceCatalog::factory()->create(['aktif' => true]);

    Livewire::actingAs($this->admin)
        ->test(CreateOrder::class)
        ->fillForm([
            'mode_pelanggan' => 'baru',
            'jenis_pelanggan' => 'company',
            'pelanggan_baru' => [
                'nama' => 'Andi Wijaya',
                'no_hp' => '628123123123',
                'area' => CustomerArea::Gowa->value,
                'sumber_lead' => LeadSource::Instagram->value,
                'email' => 'andi@test.com',
                'alamat' => 'Jl. Perintis No. 10',
            ],
            'alamat' => [
                [
                    'mode' => 'baru',
                    'alamat_baru' => [],
                    'items' => [
                        [
                            'unit_mode' => 'baru',
                            'unit_baru' => ['kode_ruangan' => 'Ruang Server', 'jenis_unit' => UnitType::Cassette->value, 'pk' => '2 PK'],
                            'service_catalog_id' => $catalog->id,
                            'jumlah' => 1,
                        ],
                    ],
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $customer = Customer::where('no_hp', '628123123123')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->nama)->toBe('Andi Wijaya')
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
        ->and($order->jenis_pelanggan->value)->toBe('company')
        ->and($order->alamat_pengerjaan)->toBe('Jl. Perintis No. 10');
});

it('mode Pelanggan Baru, unit_mode tidak_ada tetap membuat order, tanpa Unit AC', function () {
    $catalog = ServiceCatalog::factory()->create(['aktif' => true]);

    Livewire::actingAs($this->admin)
        ->test(CreateOrder::class)
        ->fillForm([
            'mode_pelanggan' => 'baru',
            'jenis_pelanggan' => 'perorangan',
            'pelanggan_baru' => [
                'nama' => 'Siti',
                'no_hp' => '628199988877',
                'area' => CustomerArea::Makassar->value,
                'alamat' => 'Jl. Sudirman 5',
            ],
            'alamat' => [
                [
                    'mode' => 'baru',
                    'alamat_baru' => [],
                    'items' => [
                        ['unit_mode' => 'tidak_ada', 'service_catalog_id' => $catalog->id, 'jumlah' => 1],
                    ],
                ],
            ],
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
    Customer::factory()->create(['no_hp' => '628177776666', 'nama' => 'Rudi Lama']);
    $catalog = ServiceCatalog::factory()->create(['aktif' => true]);

    $component = Livewire::actingAs($this->admin)
        ->test(CreateOrder::class)
        ->fillForm([
            'mode_pelanggan' => 'baru',
            'jenis_pelanggan' => 'perorangan',
            'pelanggan_baru' => [
                'nama' => 'Rudi Baru',
                'no_hp' => '628177776666',
                'area' => CustomerArea::Makassar->value,
                'alamat' => 'Jl. Baru 1',
            ],
        ]);

    $component->assertSee('Rudi Lama');

    $component->fillForm([
        'alamat' => [
            [
                'mode' => 'baru',
                'alamat_baru' => [],
                'items' => [
                    ['unit_mode' => 'tidak_ada', 'service_catalog_id' => $catalog->id, 'jumlah' => 1],
                ],
            ],
        ],
    ])->call('create')->assertHasNoFormErrors();

    // Tidak diblokir: customer baru dgn nomor sama tetap terbuat (warning, bukan guard keras).
    expect(Customer::where('no_hp', '628177776666')->count())->toBe(2);
});

it('mode Pelanggan Terdaftar (default) tetap seperti sebelumnya — tidak ada regresi', function () {
    $customer = Customer::factory()->create();
    $catalog = ServiceCatalog::factory()->create(['aktif' => true]);

    Livewire::actingAs($this->admin)
        ->test(CreateOrder::class)
        ->fillForm([
            'mode_pelanggan' => 'terdaftar',
            'customer_id' => $customer->id,
            'jenis_pelanggan' => 'perorangan',
            'alamat' => [
                [
                    'mode' => 'existing',
                    'items' => [
                        ['unit_mode' => 'tidak_ada', 'service_catalog_id' => $catalog->id, 'jumlah' => 1],
                    ],
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = Order::where('customer_id', $customer->id)->first();
    expect($order)->not->toBeNull();
});

it('regresi path relatif: field "Pilih Alamat" (Step 2) menampilkan alamat tersimpan customer setelah customer_id dipilih', function () {
    // Bug 21 Sep 2026: closure options() customer_address_id pakai
    // "../customer_id" (cuma naik 1 level lewat index item repeater
    // "alamat") — belum sampai root, jadi SELALU null & dropdown
    // SELALU kosong walau customer punya alamat tersimpan. Perbaikan:
    // "../../customer_id" (2 hop: index item + nama repeater).
    $customer = Customer::factory()->create();
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'alamat' => 'Jl. Regresi Path Relatif No. 7']);

    $component = Livewire::actingAs($this->admin)
        ->test(CreateOrder::class)
        ->fillForm(['mode_pelanggan' => 'terdaftar', 'customer_id' => $customer->id]);

    $component->assertSee('Jl. Regresi Path Relatif No. 7');
});

it('regresi path relatif: field "Pilih Unit AC" menampilkan unit tersimpan di alamat customer', function () {
    $customer = Customer::factory()->create();
    $alamat = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    CustomerAcUnit::factory()->create([
        'customer_id' => $customer->id,
        'customer_address_id' => $alamat->id,
        'kode_unit' => 'AC-REGRESI-1',
    ]);

    $component = Livewire::actingAs($this->admin)
        ->test(CreateOrder::class)
        ->fillForm([
            'mode_pelanggan' => 'terdaftar',
            'customer_id' => $customer->id,
            'alamat' => [
                [
                    'mode' => 'existing',
                    'customer_address_id' => $alamat->id,
                    'items' => [['unit_mode' => 'existing']],
                ],
            ],
        ]);

    $component->assertSee('AC-REGRESI-1');
});
