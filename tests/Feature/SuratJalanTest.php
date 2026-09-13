<?php

use App\Enums\CustomerJenis;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

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

it('pastikanSuratJalanToken membuat token sekali & idempoten', function () {
    $order = Order::factory()->create();

    $token1 = $order->pastikanSuratJalanToken();
    $token2 = $order->fresh()->pastikanSuratJalanToken();

    expect($token1)->not->toBeEmpty()
        ->and($token1)->toBe($token2);
});

it('halaman surat jalan publik menampilkan customer, jadwal, tim, & daftar pekerjaan', function () {
    $teknisi = ($this->mkTeknisi)();
    $customer = Customer::factory()->create(['nama' => 'PT Kalla Group', 'jenis' => CustomerJenis::Company]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::Terjadwal,
        'tanggal_jadwal' => '2026-10-01',
        'jam_jadwal' => '09:00',
        'alamat_pengerjaan' => 'Gedung Kalla Tower Lt. 5',
    ]);
    OrderItem::factory()->create(['order_id' => $order->id, 'nama_layanan' => 'Cuci AC Ruang Meeting', 'kategori' => ServiceType::CuciAc, 'jumlah' => 3]);
    $order->pastikanSuratJalanToken();

    $this->get(route('surat-jalan.show', [$order->id, $order->surat_jalan_token]))
        ->assertOk()
        ->assertSee('Surat Jalan')
        ->assertSee('#'.$order->id)
        ->assertSee('PT Kalla Group')
        ->assertSee('Gedung Kalla Tower Lt. 5')
        ->assertSee($order->tanggal_jadwal->translatedFormat('d M Y'))
        ->assertSee($teknisi->name)
        ->assertSee('Cuci AC Ruang Meeting');
});

it('surat jalan dengan token salah atau kosong -> 404', function () {
    $order = Order::factory()->create();
    $order->pastikanSuratJalanToken();

    $this->get(route('surat-jalan.show', [$order->id, Str::random(40)]))->assertNotFound();

    $order2 = Order::factory()->create(['surat_jalan_token' => null]);
    $this->get(route('surat-jalan.show', [$order2->id, Str::random(40)]))->assertNotFound();
});

it('aksi Surat Jalan hanya muncul utk customer instansi (company), tersembunyi utk rumahan & order batal', function () {
    $admin = ($this->mkAdmin)();
    $company = Customer::factory()->create(['jenis' => CustomerJenis::Company]);
    $perorangan = Customer::factory()->create(['jenis' => CustomerJenis::Perorangan]);
    $orderCompany = Order::factory()->create(['customer_id' => $company->id, 'jenis_pelanggan' => CustomerJenis::Company, 'status' => OrderStatus::Terjadwal]);
    $orderRumahan = Order::factory()->create(['customer_id' => $perorangan->id, 'jenis_pelanggan' => CustomerJenis::Perorangan, 'status' => OrderStatus::Terjadwal]);
    $orderBatal = Order::factory()->create(['customer_id' => $company->id, 'jenis_pelanggan' => CustomerJenis::Company, 'status' => OrderStatus::Batal]);

    Livewire::actingAs($admin)
        ->test(\App\Filament\Resources\OrderResource\Pages\ListOrders::class)
        ->assertTableActionVisible('suratJalan', $orderCompany)
        ->assertTableActionHidden('suratJalan', $orderRumahan)
        ->assertTableActionHidden('suratJalan', $orderBatal);
});

it('merender tabel admin dgn aksi Surat Jalan membuat token & link-nya valid', function () {
    $admin = ($this->mkAdmin)();
    $company = Customer::factory()->create(['jenis' => CustomerJenis::Company]);
    $order = Order::factory()->create(['customer_id' => $company->id, 'jenis_pelanggan' => CustomerJenis::Company, 'status' => OrderStatus::Terjadwal]);

    expect($order->surat_jalan_token)->toBeNull();

    Livewire::actingAs($admin)
        ->test(\App\Filament\Resources\OrderResource\Pages\ListOrders::class)
        ->assertSuccessful();

    $fresh = $order->fresh();
    expect($fresh->surat_jalan_token)->not->toBeNull();

    $this->get(route('surat-jalan.show', [$fresh->id, $fresh->surat_jalan_token]))->assertOk();
});
