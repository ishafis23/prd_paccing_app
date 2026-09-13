<?php

use App\Enums\CustomerJenis;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Teknisi\OrderDetail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\StockService;
use App\Services\TeknisiService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mkTeknisi = function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Teknisi->value);

        return $user;
    };

    $this->mkOrder = function (User $teknisi, OrderStatus $status, array $ekstra = []): Order {
        // jenis_pelanggan default Company: tes di file ini bukan tentang
        // guard bukti pembayaran (§3.7) — itu ditest di
        // OrderBuktiPembayaranTest.php — jadi dibebaskan dari syarat itu.
        return Order::factory()->create(array_merge([
            'teknisi_id' => $teknisi->id,
            'status' => $status,
            'jenis_pelanggan' => CustomerJenis::Company,
        ], $ekstra));
    };
});

it('tutupOrder mencatat waktu penutupan saat order selesai & metode sudah dipilih (B32)', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai, ['metode_dipilih' => PaymentMethod::Qris]);

    $ditutup = app(TeknisiService::class)->tutupOrder($order, $teknisi);

    expect($ditutup->ditutup_pada)->not->toBeNull()
        ->and($ditutup->sudahDitutup())->toBeTrue();
});

it('tutupOrder menolak saat order belum selesai', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Dikerjakan, ['metode_dipilih' => PaymentMethod::Qris]);

    app(TeknisiService::class)->tutupOrder($order, $teknisi);
})->throws(BusinessRuleException::class, 'selesai');

it('tutupOrder menolak saat metode belum ditandai', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai);

    app(TeknisiService::class)->tutupOrder($order, $teknisi);
})->throws(BusinessRuleException::class, 'Tandai dulu');

it('tutupOrder menolak saat order sudah lunas', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai, ['metode_dipilih' => PaymentMethod::Cash]);
    Payment::factory()->create([
        'order_id' => $order->id,
        'metode' => PaymentMethod::Cash,
        'status' => PaymentStatus::Lunas,
        'total_tagihan' => $order->total(),
        'jumlah_dibayar' => $order->total(),
    ]);

    app(TeknisiService::class)->tutupOrder($order, $teknisi);
})->throws(BusinessRuleException::class, 'lunas');

it('tutupOrder menolak penutupan ganda', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai, ['metode_dipilih' => PaymentMethod::Transfer]);

    $service = app(TeknisiService::class);
    $service->tutupOrder($order, $teknisi);

    $service->tutupOrder($order->fresh(), $teknisi);
})->throws(BusinessRuleException::class, 'sudah ditutup');

it('tutupOrder menolak teknisi yang bukan anggota tim order', function () {
    $pemilik = ($this->mkTeknisi)();
    $lain = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($pemilik, OrderStatus::Selesai, ['metode_dipilih' => PaymentMethod::Qris]);

    app(TeknisiService::class)->tutupOrder($order, $lain);
})->throws(AuthorizationException::class);

it('metode terkunci setelah order ditutup — catatMetodeDipilih ditolak (B32)', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai, ['metode_dipilih' => PaymentMethod::Qris]);

    $service = app(TeknisiService::class);
    $service->tutupOrder($order, $teknisi);

    $service->catatMetodeDipilih($order->fresh(), $teknisi, PaymentMethod::Cash);
})->throws(BusinessRuleException::class, 'terkunci');

it('slider "Selesaikan Order" muncul saat selesai + metode dipilih + belum lunas', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai, ['metode_dipilih' => PaymentMethod::Qris]);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->assertOk()
        ->assertSee('Selesaikan Order');
});

it('slider tidak muncul saat metode belum dipilih, sudah lunas, atau sudah ditutup', function (array $ekstra, string $harusTidakAda) {
    $teknisi = ($this->mkTeknisi)();

    if (($ekstra['lunas'] ?? false) === true) {
        $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai, ['metode_dipilih' => PaymentMethod::Cash]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'metode' => PaymentMethod::Cash,
            'status' => PaymentStatus::Lunas,
            'total_tagihan' => $order->total(),
            'jumlah_dibayar' => $order->total(),
        ]);
    } else {
        $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai, $ekstra['atribut'] ?? []);
    }

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->assertOk()
        ->assertDontSee($harusTidakAda);
})->with([
    'belum pilih metode' => [['atribut' => []], 'Selesaikan Order'],
    'sudah lunas' => [['lunas' => true], 'Selesaikan Order'],
    'sudah ditutup' => [['atribut' => ['metode_dipilih' => PaymentMethod::Qris, 'ditutup_pada' => now()]], 'Selesaikan Order'],
]);

it('aksi tutupOrder via Livewire menutup order; metode tampil terkunci', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai, ['metode_dipilih' => PaymentMethod::Qris]);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->call('tutupOrder')
        ->assertOk();

    expect($order->fresh()->sudahDitutup())->toBeTrue();

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order->fresh()])
        ->assertDontSee('Selesaikan Order')
        ->assertSee('Terkunci')
        ->assertDontSee('Hapus pilihan');
});
