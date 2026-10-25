<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Filament\Resources\CashHandoverResource;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('penandaan manual laporan mengisi waktu dan pengguna pengirim', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);
    $order = Order::factory()->create([
        'status' => OrderStatus::Selesai,
        'laporan_terkirim_pada' => null,
        'laporan_terkirim_oleh' => null,
    ]);

    OrderResource::tandaiLaporanTerkirim($order, $admin);

    $order->refresh();

    expect($order->laporan_terkirim_pada)->not->toBeNull()
        ->and($order->laporan_terkirim_oleh)->toBe($admin->id);
});

it('beranda teknisi menahan daftar order bila setoran tunai kemarin belum dilaporkan', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);

    $order = Order::factory()->create([
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::Selesai,
        'tanggal_jadwal' => today(),
        'metode_dipilih' => PaymentMethod::Cash,
        'metode_dipilih_pada' => now()->subDay(),
        'laporan_terkirim_pada' => now(),
    ]);
    $namaCustomer = (string) $order->customer?->nama;

    expect($namaCustomer)->not->toBe('');

    $this->actingAs($teknisi)->get('/teknisi')
        ->assertOk()
        ->assertSee('Akses ditahan')
        // daftar order disembunyikan: judul seksi & tautan detail order tidak ada
        ->assertDontSee('Hari Ini &amp; Akan Datang', escape: false)
        ->assertDontSee('/teknisi/order/'.$order->id);
});

it('beranda teknisi tetap menampilkan order bila tidak ada tunggakan setoran', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);

    $order = Order::factory()->create([
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::Selesai,
        'tanggal_jadwal' => today(),
        'laporan_terkirim_pada' => now(),
    ]);

    $this->actingAs($teknisi)->get('/teknisi')
        ->assertOk()
        ->assertDontSee('Akses ditahan')
        ->assertSee((string) $order->customer?->nama);
});

it('resource Setoran Tunai hanya untuk Owner/Admin/Finance', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);
    $finance = User::factory()->create();
    $finance->assignRole(RoleName::Finance->value);

    $this->actingAs($teknisi);
    expect(CashHandoverResource::canViewAny())->toBeFalse();

    $this->actingAs($finance);
    expect(CashHandoverResource::canViewAny())->toBeTrue();

    $this->actingAs($admin);
    expect(CashHandoverResource::canViewAny())->toBeTrue();
});
