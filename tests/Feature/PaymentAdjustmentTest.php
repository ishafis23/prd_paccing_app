<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Income;
use App\Models\Order;
use App\Models\User;
use App\Services\PaymentService;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * dev-plan/admin/03 (B77/B78) — admin bisa sesuaikan Total Tagihan saat
 * Catat Pembayaran (ongkir/material tambahan, atau diskon), wajib isi
 * alasan penyesuaian.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->paymentService = new PaymentService;
    $this->mkAdmin = fn (): User => tap(User::factory()->create(), fn (User $u) => $u->assignRole(RoleName::Admin->value));
});

function paOrder(): Order
{
    return Order::factory()->create(['status' => OrderStatus::Dikerjakan]);
}

it('total tagihan dinaikkan (ongkir/tambahan) tanpa catatan -> ditolak', function () {
    $admin = ($this->mkAdmin)();
    $order = paOrder();

    expect(fn () => $this->paymentService->recordPayment(
        $order,
        PaymentMethod::Cash,
        $order->total() + 20000,
        $admin,
        null,
        $order->total() + 20000,
    ))->toThrow(BusinessRuleException::class, 'wajib isi alasan');
});

it('total tagihan dinaikkan dgn catatan -> lunas dgn nominal disesuaikan, income ikut nominal disesuaikan', function () {
    $admin = ($this->mkAdmin)();
    $order = paOrder();
    $totalDisesuaikan = $order->total() + 20000; // ongkir tambahan

    $payment = $this->paymentService->recordPayment(
        $order,
        PaymentMethod::Cash,
        $totalDisesuaikan,
        $admin,
        null,
        $totalDisesuaikan,
        'Tambah ongkir Rp20.000 krn lokasi jauh.',
    );

    expect($payment->status)->toBe(PaymentStatus::Lunas)
        ->and((float) $payment->total_tagihan)->toBe($totalDisesuaikan)
        ->and($payment->catatan)->toBe('Tambah ongkir Rp20.000 krn lokasi jauh.');

    $income = Income::where('order_id', $order->id)->first();
    expect((float) $income->nominal)->toBe($totalDisesuaikan);
});

it('total tagihan diturunkan (diskon) dgn catatan -> lunas dgn nominal lebih rendah dari katalog', function () {
    $admin = ($this->mkAdmin)();
    $order = paOrder();
    $totalDiskon = $order->total() - 50000;

    $payment = $this->paymentService->recordPayment(
        $order,
        PaymentMethod::Cash,
        $totalDiskon,
        $admin,
        null,
        $totalDiskon,
        'Diskon pelanggan lama.',
    );

    expect($payment->status)->toBe(PaymentStatus::Lunas)
        ->and((float) $payment->total_tagihan)->toBe($totalDiskon)
        ->and($order->fresh()->status)->toBe(OrderStatus::Selesai);
});

it('DP dgn total disesuaikan lalu dilunasi tanpa override -> total disesuaikan TETAP dipakai (tidak balik ke katalog)', function () {
    $admin = ($this->mkAdmin)();
    $order = paOrder();
    $totalDisesuaikan = $order->total() + 30000;

    $dp = $this->paymentService->recordPayment(
        $order,
        PaymentMethod::Cash,
        $totalDisesuaikan * 0.5,
        $admin,
        null,
        $totalDisesuaikan,
        'Ongkir tambahan disepakati di awal.',
    );
    expect($dp->status)->toBe(PaymentStatus::Dp)
        ->and((float) $dp->total_tagihan)->toBe($totalDisesuaikan);

    // Pelunasan kedua: TIDAK kirim override & TIDAK kirim catatan lagi —
    // harus tetap berhasil krn total yg berlaku sudah tersimpan di baris
    // payment yg sama (baseline), bukan wajib catatan ulang.
    $sisa = $totalDisesuaikan - (float) $dp->jumlah_dibayar;
    $lunas = $this->paymentService->recordPayment($order, PaymentMethod::Qris, $sisa, $admin);

    expect($lunas->status)->toBe(PaymentStatus::Lunas)
        ->and((float) $lunas->total_tagihan)->toBe($totalDisesuaikan);
});

it('jumlah bayar tetap ditolak kalau melebihi total yg sudah disesuaikan', function () {
    $admin = ($this->mkAdmin)();
    $order = paOrder();
    $totalDisesuaikan = $order->total() + 30000;

    expect(fn () => $this->paymentService->recordPayment(
        $order,
        PaymentMethod::Cash,
        $totalDisesuaikan + 10000,
        $admin,
        null,
        $totalDisesuaikan,
        'Ongkir tambahan.',
    ))->toThrow(BusinessRuleException::class, 'melebihi sisa');
});
