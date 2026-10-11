<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\AkuntanService;
use App\Services\FinanceService;
use App\Services\OmsetService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;

/**
 * Omset Jasa & Omset Material di "Catat Pembayaran" (dev-plan/24) — split
 * disimpan di payments, dipakai sebagai acuan final laporan omset & keuangan,
 * dengan fallback ke order_items bila belum diisi.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);

    $this->paymentService = fn (): PaymentService => app(PaymentService::class);

    // Order: 3 unit Cuci AC @100rb (jasa 300rb) + Ganti Kapasitor 250rb
    // (material) => total tagihan 550rb. Split dari items: jasa 300 / material 250.
    $this->buatOrder = function (): Order {
        $catalog = ServiceCatalog::factory()->create(['jenis_layanan' => ServiceType::CuciAc, 'harga' => 100000]);
        $order = Order::factory()->create([
            'service_catalog_id' => $catalog->id,
            'jumlah_unit' => 3,
            'status' => OrderStatus::Dikerjakan,
        ]);
        app(OrderService::class)->tambahLayanan(
            $order,
            ['nama_layanan' => 'Ganti Kapasitor', 'kategori' => null, 'harga' => 250000, 'jumlah' => 1],
            $this->admin,
        );

        return $order->fresh();
    };
});

it('recordPayment menyimpan split omset jasa/material & laporan memakai split', function () {
    $order = ($this->buatOrder)();
    expect($order->total())->toBe(550000.0);

    $payment = ($this->paymentService)()->recordPayment(
        $order,
        PaymentMethod::Cash,
        550000,
        $this->admin,
        '2026-10-05',
        null,
        null,
        150000,
        400000,
    );

    expect($payment->punyaSplitOmset())->toBeTrue();
    expect($payment->splitOmset())->toBe(['jasa' => 150000.0, 'material' => 400000.0]);

    $omset = app(OmsetService::class);
    $rows = $omset->ringkasan(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));
    $total = $omset->total($rows);

    expect($total['jasa'])->toBe(150000.0);
    expect($total['material'])->toBe(400000.0);
    expect($total['total'])->toBe(550000.0);
});

it('akuntan & laba-rugi memakai split pembayaran sebagai acuan final', function () {
    $order = ($this->buatOrder)();
    ($this->paymentService)()->recordPayment($order, PaymentMethod::Cash, 550000, $this->admin, '2026-10-05', null, null, 150000, 400000);

    $dari = Carbon::parse('2026-10-01');
    $sampai = Carbon::parse('2026-10-31');

    $rows = app(AkuntanService::class)->pendapatan($dari, $sampai);
    expect($rows->first()['total_jasa'])->toBe(150000.0);
    expect($rows->first()['total_material'])->toBe(400000.0);
    expect($rows->first()['total'])->toBe(550000.0);

    $lr = app(FinanceService::class)->labaRugi($dari, $sampai);
    expect($lr['pendapatan_jasa'])->toBe(150000.0);
    expect($lr['pendapatan_material'])->toBe(400000.0);
});

it('tanpa split pembayaran, laporan jatuh ke rincian order_items (fallback)', function () {
    $order = ($this->buatOrder)();
    ($this->paymentService)()->recordPayment($order, PaymentMethod::Cash, 550000, $this->admin, '2026-10-05');

    $omset = app(OmsetService::class);
    $total = $omset->total($omset->ringkasan(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31')));

    expect($total['jasa'])->toBe(300000.0);
    expect($total['material'])->toBe(250000.0);
    expect($total['total'])->toBe(550000.0);
});

it('recordPayment menolak bila Jasa + Material tidak sama dengan Total Tagihan', function () {
    $order = ($this->buatOrder)();

    expect(fn () => ($this->paymentService)()->recordPayment(
        $order, PaymentMethod::Cash, 550000, $this->admin, null, null, null, 100000, 400000,
    ))->toThrow(BusinessRuleException::class, 'harus sama dengan Total Tagihan');
});

it('recordPayment menolak split negatif', function () {
    $order = ($this->buatOrder)();

    expect(fn () => ($this->paymentService)()->recordPayment(
        $order, PaymentMethod::Cash, 550000, $this->admin, null, null, null, -1000, 551000,
    ))->toThrow(BusinessRuleException::class, 'tidak boleh negatif');
});

it('split lama dipertahankan saat pelunasan lanjutan tanpa mengisi ulang', function () {
    $order = ($this->buatOrder)();

    $dp = ($this->paymentService)()->recordPayment($order, PaymentMethod::Cash, 200000, $this->admin, '2026-10-01', null, null, 150000, 400000);
    expect($dp->status)->toBe(PaymentStatus::Dp);

    $lunas = ($this->paymentService)()->recordPayment($order->fresh(), PaymentMethod::Cash, 350000, $this->admin, '2026-10-05');

    expect($lunas->status)->toBe(PaymentStatus::Lunas);
    expect($lunas->splitOmset())->toBe(['jasa' => 150000.0, 'material' => 400000.0]);
});

it('menolak saat Total Tagihan berubah tetapi split lama tidak lagi cocok', function () {
    $order = ($this->buatOrder)();
    ($this->paymentService)()->recordPayment($order, PaymentMethod::Cash, 200000, $this->admin, null, null, null, 150000, 400000);

    expect(fn () => ($this->paymentService)()->recordPayment(
        $order->fresh(), PaymentMethod::Cash, 100000, $this->admin, null, 600000, 'tambah ongkir',
    ))->toThrow(BusinessRuleException::class, 'isi ulang Omset Jasa');
});
