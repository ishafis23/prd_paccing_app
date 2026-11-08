<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Exceptions\BusinessRuleException;
use App\Models\Income;
use App\Models\Order;
use App\Models\OrderTotalCorrection;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Laporan client: tagihan awal 170rb sudah dicatat bayar, lalu "Koreksi Total" jadi 185rb.
 * Dulu "Edit Omset Pembayaran" tetap menuntut Jasa+Material = 170rb (tagihan awal) karena
 * payments.total_tagihan tidak ikut berubah. Kini pembayaran diselaraskan dengan koreksi.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);

    $catalog = ServiceCatalog::factory()->create(['jenis_layanan' => ServiceType::CuciAc, 'harga' => 170000]);
    $this->order = Order::factory()->create([
        'service_catalog_id' => $catalog->id,
        'jumlah_unit' => 1,
        'status' => OrderStatus::Dikerjakan,
    ]);

    $this->bayar = fn (float $jumlah, ?float $total = null, ?string $catatan = null) => app(PaymentService::class)->recordPayment(
        $this->order->fresh(), PaymentMethod::Cash, $jumlah, $this->admin, null, $total, $catatan,
    );
    $this->koreksi = fn (float $total) => app(OrderService::class)->koreksiTotal($this->order->fresh(), $total, 'Ada tambahan material', $this->admin);
});

it('LUNAS: koreksi total ke 185rb — tagihan & dibayar ikut 185rb, tetap lunas, income & split ikut, Edit Omset ke 185rb berhasil', function () {
    // Catat bayar lunas 170rb dengan split omset jasa 170rb / material 0.
    $payment = app(PaymentService::class)->recordPayment(
        $this->order->fresh(), PaymentMethod::Cash, 170000, $this->admin, null, null, null, 170000, 0,
    );
    expect($payment->status)->toBe(PaymentStatus::Lunas)
        ->and((float) Income::where('order_id', $this->order->id)->sum('nominal'))->toBe(170000.0);

    ($this->koreksi)(185000);

    $payment = $this->order->fresh()->payments()->first();
    expect((float) $payment->total_tagihan)->toBe(185000.0)
        ->and((float) $payment->jumlah_dibayar)->toBe(185000.0)
        ->and($payment->status)->toBe(PaymentStatus::Lunas)
        ->and((float) $payment->omset_jasa + (float) $payment->omset_material)->toBe(185000.0)
        ->and((float) Income::where('order_id', $this->order->id)->sum('nominal'))->toBe(185000.0)
        ->and($payment->catatan)->toContain('selisih dianggap sudah dibayar');

    // Skenario yang dilaporkan client: edit omset terhadap tagihan baru.
    $hasil = app(PaymentService::class)->koreksiSplitOmset($this->order->fresh(), 100000, 85000, $this->admin);
    expect((float) $hasil->omset_jasa)->toBe(100000.0)->and((float) $hasil->omset_material)->toBe(85000.0);
});

it('DP: koreksi total menaikkan total tagihan saja; sisa bertambah, status tetap DP', function () {
    ($this->bayar)(100000, 170000);

    ($this->koreksi)(185000);

    $payment = $this->order->fresh()->payments()->first();
    expect((float) $payment->total_tagihan)->toBe(185000.0)
        ->and((float) $payment->jumlah_dibayar)->toBe(100000.0)
        ->and($payment->status)->toBe(PaymentStatus::Dp);

    // Bayar sisa 85rb → lunas di 185rb.
    $lunas = ($this->bayar)(85000);
    expect($lunas->status)->toBe(PaymentStatus::Lunas)->and((float) $lunas->total_tagihan)->toBe(185000.0);
});

it('DP: koreksi turun di bawah jumlah yang sudah dibayar ditolak dan koreksi dibatalkan', function () {
    ($this->bayar)(150000, 170000);

    expect(fn () => ($this->koreksi)(120000))->toThrow(BusinessRuleException::class, 'tidak boleh di bawah jumlah yang sudah dibayar');

    expect(OrderTotalCorrection::where('order_id', $this->order->id)->count())->toBe(0)
        ->and($this->order->fresh()->total())->toBe(170000.0);
});

it('diskon sengaja admin (total pembayaran < total order) tidak ditimpa saat Edit Omset', function () {
    // Total order 170rb tetapi admin menetapkan tagihan 150rb (diskon) dan DP 100rb.
    ($this->bayar)(100000, 150000, 'Diskon pelanggan lama');

    $hasil = app(PaymentService::class)->koreksiSplitOmset($this->order->fresh(), 100000, 50000, $this->admin);

    expect((float) $hasil->total_tagihan)->toBe(150000.0);
});

it('data lama: koreksi dibuat sebelum perbaikan (belum tersinkron) disembuhkan saat Edit Omset', function () {
    $payment = ($this->bayar)(170000);

    // Meniru kondisi lama: baris penyesuaian + catatan koreksi ada, payment tidak tersentuh.
    $this->order->orderItems()->create([
        'nama_layanan' => 'Penyesuaian total', 'kategori' => null, 'komponen' => \App\Enums\IncomeCategory::Jasa,
        'penyesuaian' => true, 'harga' => 15000, 'jumlah' => 1, 'ditambahkan_oleh' => $this->admin->id,
    ]);
    OrderTotalCorrection::create([
        'order_id' => $this->order->id, 'total_original' => 170000, 'total_terkoreksi' => 185000,
        'alasan' => 'Ada tambahan', 'dikoreksi_oleh' => $this->admin->id,
    ]);
    expect((float) $payment->fresh()->total_tagihan)->toBe(170000.0);

    $hasil = app(PaymentService::class)->koreksiSplitOmset($this->order->fresh(), 185000, 0, $this->admin);

    expect((float) $hasil->total_tagihan)->toBe(185000.0)
        ->and((float) $hasil->jumlah_dibayar)->toBe(185000.0);
});

it('idempotent: sinkron dua kali tidak menghitung koreksi dobel', function () {
    ($this->bayar)(170000);
    ($this->koreksi)(185000);

    app(PaymentService::class)->sinkronkanTotalDenganOrder($this->order->fresh(), $this->admin);
    app(PaymentService::class)->sinkronkanTotalDenganOrder($this->order->fresh(), $this->admin);

    expect((float) $this->order->fresh()->payments()->first()->total_tagihan)->toBe(185000.0);
});

it('koreksi total pada order tanpa pembayaran tidak berefek ke payment', function () {
    ($this->koreksi)(185000);

    expect($this->order->fresh()->payments()->count())->toBe(0)->and($this->order->fresh()->total())->toBe(185000.0);
});
