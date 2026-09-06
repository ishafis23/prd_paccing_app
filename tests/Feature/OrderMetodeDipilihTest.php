<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\StockService;
use App\Services\TeknisiService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->teknisiService = new TeknisiService(new StockService);
});

function ompTeknisi(): User
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::Teknisi->value);

    return $user;
}

function ompOrder(User $teknisi, OrderStatus $status): Order
{
    return Order::factory()->create([
        'teknisi_id' => $teknisi->id,
        'status' => $status,
    ]);
}

it('teknisi PIC dapat menandai metode yang dipilih customer (B13b)', function () {
    $teknisi = ompTeknisi();
    $order = ompOrder($teknisi, OrderStatus::Selesai);

    $updated = $this->teknisiService->catatMetodeDipilih($order, $teknisi, PaymentMethod::Qris);

    expect($updated->metode_dipilih)->toBe(PaymentMethod::Qris);
});

it('teknisi dapat menghapus tanda metode (mengosongkan)', function () {
    $teknisi = ompTeknisi();
    $order = ompOrder($teknisi, OrderStatus::Selesai);

    $this->teknisiService->catatMetodeDipilih($order, $teknisi, PaymentMethod::Cash);
    $updated = $this->teknisiService->catatMetodeDipilih($order, $teknisi, null);

    expect($updated->metode_dipilih)->toBeNull();
});

it('teknisi lain tidak boleh menandai metode order orang lain', function () {
    $pemilik = ompTeknisi();
    $lain = ompTeknisi();
    $order = ompOrder($pemilik, OrderStatus::Selesai);

    $this->teknisiService->catatMetodeDipilih($order, $lain, PaymentMethod::Qris);
})->throws(AuthorizationException::class);

it('order batal tidak bisa ditandai metodenya', function () {
    $teknisi = ompTeknisi();
    $order = ompOrder($teknisi, OrderStatus::Batal);

    $this->teknisiService->catatMetodeDipilih($order, $teknisi, PaymentMethod::Cash);
})->throws(BusinessRuleException::class, 'batal');

it('order yang sudah lunas tidak bisa diubah metodenya', function () {
    $teknisi = ompTeknisi();
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);
    $order = ompOrder($teknisi, OrderStatus::Selesai);

    Payment::factory()->create([
        'order_id' => $order->id,
        'metode' => PaymentMethod::Cash,
        'status' => PaymentStatus::Lunas,
        'total_tagihan' => $order->total(),
        'jumlah_dibayar' => $order->total(),
        'dicatat_oleh' => $admin->id,
    ]);

    $this->teknisiService->catatMetodeDipilih($order, $teknisi, PaymentMethod::Qris);
})->throws(BusinessRuleException::class, 'lunas');
