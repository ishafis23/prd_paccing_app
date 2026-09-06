<?php

use App\Enums\AttendanceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Attendance;
use App\Models\Order;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WorkReport;
use App\Models\WorkReportMaterial;
use App\Services\PaymentService;
use App\Services\StockService;
use App\Services\TeknisiService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->teknisiService = new TeknisiService(app(StockService::class));
    $this->paymentService = new PaymentService;
    $this->mkTeknisi = fn (): User => tap(User::factory()->create(), fn (User $u) => $u->assignRole(RoleName::Teknisi->value));
    $this->mkAdmin = fn (): User => tap(User::factory()->create(), fn (User $u) => $u->assignRole(RoleName::Admin->value));
});

function hrdnOrderDikerjakan(User $teknisi): Order
{
    $order = Order::factory()->create([
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::Dikerjakan,
    ]);
    Attendance::create([
        'user_id' => $teknisi->id,
        'order_id' => $order->id,
        'tanggal' => now()->toDateString(),
        'jam_masuk' => now(),
        'status' => AttendanceStatus::Hadir,
    ]);

    return $order;
}

it('submit laporan dengan material kedua invalid -> rollback total (tanpa paruh-tulis)', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = hrdnOrderDikerjakan($teknisi);
    $item = StockItem::factory()->create(['stok_saat_ini' => 5]);

    expect(fn () => $this->teknisiService->submitLaporan($order, $teknisi, [
        'catatan' => 'Selesai dikerjakan.',
        'materials' => [
            ['stock_item_id' => $item->id, 'jumlah' => 2],
            ['stock_item_id' => 999999, 'jumlah' => 1],
        ],
    ]))->toThrow(BusinessRuleException::class);

    $order->refresh();

    expect(WorkReport::query()->count())->toBe(0)
        ->and(WorkReportMaterial::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and($item->fresh()->stok_saat_ini)->toBe(5)
        ->and($order->status)->toBe(OrderStatus::Dikerjakan);
});

it('submit laporan dengan jumlah 0 di baris kedua -> rollback total', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = hrdnOrderDikerjakan($teknisi);
    $itemA = StockItem::factory()->create(['stok_saat_ini' => 3]);
    $itemB = StockItem::factory()->create(['stok_saat_ini' => 3]);

    expect(fn () => $this->teknisiService->submitLaporan($order, $teknisi, [
        'catatan' => 'Selesai dikerjakan.',
        'materials' => [
            ['stock_item_id' => $itemA->id, 'jumlah' => 1],
            ['stock_item_id' => $itemB->id, 'jumlah' => 0],
        ],
    ]))->toThrow(BusinessRuleException::class, 'Jumlah material');

    expect(WorkReport::query()->count())->toBe(0)
        ->and(WorkReportMaterial::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and($itemA->fresh()->stok_saat_ini)->toBe(3)
        ->and($itemB->fresh()->stok_saat_ini)->toBe(3);
});

it('pembayaran melebihi sisa tagihan ditolak', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create([
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::Dikerjakan,
    ]);

    $total = $order->total();
    $dpN = (int) round($total * 0.4);

    $dp = $this->paymentService->recordPayment($order, PaymentMethod::Cash, $dpN, $admin);
    expect($dp->status)->toBe(PaymentStatus::Dp);

    $sisa = $total - (float) $dp->jumlah_dibayar;

    expect(fn () => $this->paymentService->recordPayment(
        $order,
        PaymentMethod::Qris,
        $sisa + 50000,
        $admin
    ))->toThrow(BusinessRuleException::class, 'melebihi sisa');

    $payment = $order->payments()->first();

    expect((float) $payment->jumlah_dibayar)->toBe((float) $dpN)
        ->and($payment->status)->toBe(PaymentStatus::Dp);
});

it('pelunasan tepat sisa tetap berhasil dan mengunci lunas', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create([
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::Dikerjakan,
    ]);

    $this->paymentService->recordPayment($order, PaymentMethod::Cash, $order->total() * 0.4, $admin);
    $lunas = $this->paymentService->recordPayment($order, PaymentMethod::Qris, $order->total() * 0.6, $admin);

    expect($lunas->status)->toBe(PaymentStatus::Lunas);
});

it('pembayaran dengan nominal 0 atau negatif tetap ditolak', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);

    expect(fn () => $this->paymentService->recordPayment($order, PaymentMethod::Cash, 0, $admin))
        ->toThrow(BusinessRuleException::class, 'lebih dari 0');
});
