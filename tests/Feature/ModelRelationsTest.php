<?php

use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ServiceCatalog;
use App\Models\ServiceReminder;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WorkReport;
use App\Models\WorkReportMaterial;

it('order terhubung ke customer, katalog, teknisi, pembayaran, laporan & reminder', function () {
    $teknisi = User::factory()->create();
    $order = Order::factory()->terjadwal()->create(['teknisi_id' => $teknisi->id]);

    expect($order->customer)->toBeInstanceOf(Customer::class)
        ->and($order->serviceCatalog)->toBeInstanceOf(ServiceCatalog::class)
        ->and($order->teknisi->id)->toBe($teknisi->id)
        ->and($order->creator)->toBeInstanceOf(User::class);

    $payment = Payment::factory()->create(['order_id' => $order->id]);
    $report = WorkReport::factory()->create([
        'order_id' => $order->id,
        'teknisi_id' => $teknisi->id,
    ]);
    $reminder = ServiceReminder::factory()->create([
        'customer_id' => $order->customer_id,
        'order_id' => $order->id,
    ]);

    expect($order->payments)->toHaveCount(1)
        ->and($order->latestPayment->id)->toBe($payment->id)
        ->and($order->workReports()->count())->toBe(1)
        ->and($order->serviceReminder->id)->toBe($reminder->id);
});

it('total order = harga katalog x jumlah unit', function () {
    $catalog = ServiceCatalog::factory()->create(['harga' => 150000]);
    $order = Order::factory()->create([
        'service_catalog_id' => $catalog->id,
        'jumlah_unit' => 3,
    ]);

    expect($order->total())->toBe(450000.0);
});

it('customer memiliki banyak order dan reminder', function () {
    $customer = Customer::factory()->create();
    Order::factory()->count(2)->create(['customer_id' => $customer->id]);
    ServiceReminder::factory()->count(2)->create(['customer_id' => $customer->id]);

    expect($customer->orders)->toHaveCount(2)
        ->and($customer->serviceReminders)->toHaveCount(2);
});

it('laporan pengerjaan berisi material yang dipilih dari stok', function () {
    $report = WorkReport::factory()->create();
    $item = StockItem::factory()->create();

    $material = WorkReportMaterial::factory()->create([
        'work_report_id' => $report->id,
        'stock_item_id' => $item->id,
        'jumlah' => 2,
    ]);

    expect($report->materials)->toHaveCount(1)
        ->and($material->stockItem->id)->toBe($item->id)
        ->and($material->workReport->id)->toBe($report->id);
});

it('stok memiliki kartu movement dan scope menipis', function () {
    $item = StockItem::factory()->create(['stok_saat_ini' => 1, 'stok_minimum' => 3]);
    StockMovement::factory()->count(2)->create(['stock_item_id' => $item->id]);

    expect($item->movements)->toHaveCount(2)
        ->and(StockItem::menipis()->pluck('id'))->toContain($item->id);
});

it('absensi terkait user dan order (check-in di lokasi)', function () {
    $teknisi = User::factory()->create();
    $order = Order::factory()->create();

    $attendance = Attendance::factory()->create([
        'user_id' => $teknisi->id,
        'order_id' => $order->id,
    ]);

    expect($attendance->user->id)->toBe($teknisi->id)
        ->and($attendance->order->id)->toBe($order->id);
});

it('income dan expense terhubung ke sumbernya', function () {
    $order = Order::factory()->create();
    $income = Income::factory()->create(['order_id' => $order->id]);
    $expense = Expense::factory()->create();

    expect($income->order->id)->toBe($order->id)
        ->and($expense->recordedBy)->toBeInstanceOf(User::class);
});
