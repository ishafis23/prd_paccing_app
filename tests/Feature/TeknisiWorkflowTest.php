<?php

use App\Enums\AttendanceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Attendance;
use App\Models\Income;
use App\Models\Order;
use App\Models\ServiceReminder;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WorkReport;
use App\Models\WorkReportMaterial;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\StockService;
use App\Services\TeknisiService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->teknisiService = new TeknisiService(app(StockService::class));
    $this->paymentService = new PaymentService;
});

function teknisiUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::Teknisi->value);

    return $user;
}

function orderUntuk(User $teknisi, OrderStatus $status = OrderStatus::Terjadwal): Order
{
    return Order::factory()->create([
        'teknisi_id' => $teknisi->id,
        'status' => $status,
    ]);
}

it('teknisi menggeser slider berangkat -> status menuju_lokasi', function () {
    $teknisi = teknisiUser();
    $order = orderUntuk($teknisi);

    $updated = $this->teknisiService->berangkat($order, $teknisi);

    expect($updated->status)->toBe(OrderStatus::MenujuLokasi);
});

it('teknisi tidak bisa berangkat untuk order milik teknisi lain', function () {
    $teknisiA = teknisiUser();
    $teknisiB = teknisiUser();
    $order = orderUntuk($teknisiB);

    $this->teknisiService->berangkat($order, $teknisiA);
})->throws(AuthorizationException::class);

it('berangkat hanya valid dari status terjadwal', function () {
    $teknisi = teknisiUser();
    $order = orderUntuk($teknisi, OrderStatus::Baru);

    $this->teknisiService->berangkat($order, $teknisi);
})->throws(BusinessRuleException::class);

it('update lokasi menyimpan posisi GPS terakhir teknisi selagi menuju_lokasi', function () {
    $teknisi = teknisiUser();
    $order = orderUntuk($teknisi, OrderStatus::MenujuLokasi);

    $this->teknisiService->updateLokasi($order, $teknisi, -5.147665, 119.432732);

    $fresh = $teknisi->fresh();
    expect((float) $fresh->last_latitude)->toBe(-5.147665)
        ->and((float) $fresh->last_longitude)->toBe(119.432732)
        ->and($fresh->last_location_at)->not->toBeNull();
});

it('update lokasi ditolak kalau order belum menuju_lokasi/dikerjakan', function () {
    $teknisi = teknisiUser();
    $order = orderUntuk($teknisi, OrderStatus::Terjadwal);

    $this->teknisiService->updateLokasi($order, $teknisi, -5.147665, 119.432732);
})->throws(BusinessRuleException::class);

it('update lokasi ditolak untuk teknisi yang bukan anggota order', function () {
    $teknisiA = teknisiUser();
    $teknisiB = teknisiUser();
    $order = orderUntuk($teknisiB, OrderStatus::MenujuLokasi);

    $this->teknisiService->updateLokasi($order, $teknisiA, -5.147665, 119.432732);
})->throws(AuthorizationException::class);

it('check-in membuat attendance dan mengubah status jadi dikerjakan', function () {
    $teknisi = teknisiUser();
    $order = orderUntuk($teknisi, OrderStatus::MenujuLokasi);

    $attendance = $this->teknisiService->checkIn($order, $teknisi, 'Jl. Customer No. 5, Makassar');

    expect($attendance)->toBeInstanceOf(Attendance::class)
        ->and($attendance->user_id)->toBe($teknisi->id)
        ->and($attendance->order_id)->toBe($order->id)
        ->and($attendance->status)->toBe(AttendanceStatus::Hadir)
        ->and($attendance->lokasi)->toBe('Jl. Customer No. 5, Makassar')
        ->and($order->fresh()->status)->toBe(OrderStatus::Dikerjakan);
});

it('check-in wajib lewat status menuju_lokasi (slider dulu)', function () {
    $teknisi = teknisiUser();
    $order = orderUntuk($teknisi, OrderStatus::Terjadwal);

    $this->teknisiService->checkIn($order, $teknisi);
})->throws(BusinessRuleException::class);

it('submit laporan -> work report, stok keluar, status selesai, check-out', function () {
    $teknisi = teknisiUser();
    $order = orderUntuk($teknisi, OrderStatus::Dikerjakan);

    Attendance::factory()->create([
        'user_id' => $teknisi->id,
        'order_id' => $order->id,
        'jam_masuk' => now()->subHours(2),
    ]);

    $freon = StockItem::factory()->create(['stok_saat_ini' => 3]);

    $report = $this->teknisiService->submitLaporan($order, $teknisi, [
        'catatan' => 'Cuci indoor & outdoor, freon ditambah.',
        'materials' => [
            ['stock_item_id' => $freon->id, 'jumlah' => 1],
        ],
        'foto_sebelum' => 'before.jpg',
        'foto_sesudah' => 'after.jpg',
    ]);

    expect($report)->toBeInstanceOf(WorkReport::class)
        ->and($report->foto_sebelum)->toBe('before.jpg');

    expect($report->materials)->toHaveCount(1)
        ->and($report->materials->first()->stock_item_id)->toBe($freon->id);

    expect($freon->fresh()->stok_saat_ini)->toBe(2);

    $movement = StockMovement::where('stock_item_id', $freon->id)->first();
    expect($movement)->not->toBeNull()
        ->and($movement->referensi)->toBe('work_report:'.$report->id);

    expect($order->fresh()->status)->toBe(OrderStatus::Selesai);

    $attendance = Attendance::where('order_id', $order->id)->first();
    expect($attendance->jam_keluar)->not->toBeNull();
});

it('laporan dengan flag follow-up -> status butuh_followup', function () {
    $teknisi = teknisiUser();
    $order = orderUntuk($teknisi, OrderStatus::Dikerjakan);

    $this->teknisiService->submitLaporan($order, $teknisi, [
        'catatan' => 'Sparepart kurang, perlu order baru.',
        'materials' => [],
        'butuh_followup' => true,
    ]);

    expect($order->fresh()->status)->toBe(OrderStatus::ButuhFollowup);
});

it('laporan wajib punya catatan pengerjaan', function () {
    $teknisi = teknisiUser();
    $order = orderUntuk($teknisi, OrderStatus::Dikerjakan);

    $this->teknisiService->submitLaporan($order, $teknisi, [
        'catatan' => '   ',
        'materials' => [],
    ]);
})->throws(BusinessRuleException::class);

it('submit laporan memakai stok melebihi saldo -> stok minus (keputusan B4)', function () {
    $teknisi = teknisiUser();
    $order = orderUntuk($teknisi, OrderStatus::Dikerjakan);
    $item = StockItem::factory()->create(['stok_saat_ini' => 1]);

    $this->teknisiService->submitLaporan($order, $teknisi, [
        'catatan' => 'Pakai banyak.',
        'materials' => [['stock_item_id' => $item->id, 'jumlah' => 4]],
    ]);

    expect($item->fresh()->stok_saat_ini)->toBe(-3);
});

it('end-to-end: order -> berangkat -> check-in -> laporan -> lunas -> income & reminder', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);
    $teknisi = teknisiUser();

    $order = orderUntuk($teknisi, OrderStatus::Terjadwal);
    $catalog = $order->serviceCatalog; // interval 3 bulan dari factory

    $this->teknisiService->berangkat($order, $teknisi);
    $this->teknisiService->checkIn($order, $teknisi);
    $this->teknisiService->submitLaporan($order, $teknisi, [
        'catatan' => 'Selesai semua.',
        'materials' => [],
    ]);

    expect($order->fresh()->status)->toBe(OrderStatus::Selesai);

    // §3.11: pelunasan ditahan sampai laporan diverifikasi admin.
    $laporan = WorkReport::where('order_id', $order->id)->latest('id')->first();
    app(OrderService::class)->verifikasiLaporan($laporan, $admin);

    $this->paymentService->recordPayment($order->fresh(), PaymentMethod::Cash, $order->total(), $admin);

    expect(Income::where('order_id', $order->id)->count())->toBe(1)
        ->and(ServiceReminder::where('order_id', $order->id)->count())->toBe(1)
        ->and($catalog->interval_bulan)->toBe(3);

    // Work report sudah ada persis satu
    expect(WorkReportMaterial::whereIn('work_report_id', WorkReport::where('order_id', $order->id)->pluck('id'))->count())->toBe(0);
});
