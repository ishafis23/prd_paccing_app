<?php

use App\Enums\CustomerJenis;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Teknisi\OrderDetail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\OrderService;
use App\Services\TeknisiService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');

    $this->mkTeknisi = function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Teknisi->value);

        return $user;
    };

    $this->mkAdmin = function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    };

    $this->mkOrder = function (User $teknisi, OrderStatus $status, array $ekstra = []): Order {
        return Order::factory()->create(array_merge([
            'teknisi_id' => $teknisi->id,
            'status' => $status,
            'metode_dipilih' => PaymentMethod::Cash,
        ], $ekstra));
    };
});

it('createOrder default jenis_pelanggan mengikuti jenis customer', function () {
    $admin = ($this->mkAdmin)();
    $companyCustomer = Customer::factory()->create(['jenis' => CustomerJenis::Company]);
    $catalog = \App\Models\ServiceCatalog::factory()->create();

    $order = app(OrderService::class)->createOrder([
        'customer_id' => $companyCustomer->id,
        'service_catalog_id' => $catalog->id,
    ], $admin);

    expect($order->jenis_pelanggan)->toBe(CustomerJenis::Company);
});

it('createOrder bisa override jenis_pelanggan walau beda dari customer', function () {
    $admin = ($this->mkAdmin)();
    $perorangan = Customer::factory()->create(['jenis' => CustomerJenis::Perorangan]);
    $catalog = \App\Models\ServiceCatalog::factory()->create();

    $order = app(OrderService::class)->createOrder([
        'customer_id' => $perorangan->id,
        'service_catalog_id' => $catalog->id,
        'jenis_pelanggan' => CustomerJenis::Company->value,
    ], $admin);

    expect($order->jenis_pelanggan)->toBe(CustomerJenis::Company);
});

it('uploadBuktiPembayaran menyimpan path & bisa diganti sebelum ditutup', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai);

    $hasil = app(TeknisiService::class)->uploadBuktiPembayaran($order, $teknisi, 'bukti-pembayaran/a.jpg');
    expect($hasil->bukti_pembayaran)->toBe('bukti-pembayaran/a.jpg');

    $hasil2 = app(TeknisiService::class)->uploadBuktiPembayaran($order->fresh(), $teknisi, 'bukti-pembayaran/b.jpg');
    expect($hasil2->bukti_pembayaran)->toBe('bukti-pembayaran/b.jpg');
});

it('uploadBuktiPembayaran ditolak setelah order ditutup', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai, [
        'jenis_pelanggan' => CustomerJenis::Company,
        'ditutup_pada' => now(),
    ]);

    app(TeknisiService::class)->uploadBuktiPembayaran($order, $teknisi, 'bukti-pembayaran/a.jpg');
})->throws(BusinessRuleException::class, 'sudah ditutup');

it('uploadBuktiPembayaran menolak teknisi bukan anggota tim', function () {
    $pemilik = ($this->mkTeknisi)();
    $lain = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($pemilik, OrderStatus::Selesai);

    app(TeknisiService::class)->uploadBuktiPembayaran($order, $lain, 'bukti-pembayaran/a.jpg');
})->throws(AuthorizationException::class);

it('tutupOrder menolak customer rumahan tanpa bukti pembayaran', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai, ['jenis_pelanggan' => CustomerJenis::Perorangan]);

    app(TeknisiService::class)->tutupOrder($order, $teknisi);
})->throws(BusinessRuleException::class, 'bukti pembayaran');

it('tutupOrder menolak order tanpa jenis_pelanggan (legacy) & belum ada bukti', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai, ['jenis_pelanggan' => null]);

    app(TeknisiService::class)->tutupOrder($order, $teknisi);
})->throws(BusinessRuleException::class, 'bukti pembayaran');

it('tutupOrder berhasil utk rumahan setelah bukti pembayaran diupload', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai, ['jenis_pelanggan' => CustomerJenis::Perorangan]);

    app(TeknisiService::class)->uploadBuktiPembayaran($order, $teknisi, 'bukti-pembayaran/a.jpg');

    $hasil = app(TeknisiService::class)->tutupOrder($order->fresh(), $teknisi);

    expect($hasil->sudahDitutup())->toBeTrue();
});

it('tutupOrder berhasil utk instansi TANPA bukti pembayaran', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai, ['jenis_pelanggan' => CustomerJenis::Company]);

    $hasil = app(TeknisiService::class)->tutupOrder($order, $teknisi);

    expect($hasil->sudahDitutup())->toBeTrue();
});

it('livewire uploadBuktiPembayaran menyimpan file ke disk & order', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai, ['jenis_pelanggan' => CustomerJenis::Perorangan]);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->set('buktiPembayaran', UploadedFile::fake()->image('bukti.jpg'))
        ->call('uploadBuktiPembayaran')
        ->assertOk()
        ->assertHasNoErrors();

    $fresh = $order->fresh();
    expect($fresh->bukti_pembayaran)->not->toBeNull();
    Storage::disk('public')->assertExists($fresh->bukti_pembayaran);
});

it('teks wajib/opsional bukti pembayaran tampil sesuai jenis_pelanggan', function () {
    $teknisi = ($this->mkTeknisi)();

    $rumahan = ($this->mkOrder)($teknisi, OrderStatus::Selesai, ['jenis_pelanggan' => CustomerJenis::Perorangan]);
    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $rumahan])
        ->assertSee('*wajib');

    $instansi = ($this->mkOrder)($teknisi, OrderStatus::Selesai, ['jenis_pelanggan' => CustomerJenis::Company]);
    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $instansi])
        ->assertSee('opsional utk instansi');
});
