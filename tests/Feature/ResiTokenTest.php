<?php

use App\Enums\AttendanceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Models\Attendance;
use App\Models\Order;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\StockService;
use App\Services\TeknisiService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->teknisiService = new TeknisiService(app(StockService::class));
    $this->paymentService = new PaymentService;
});

function resiTeknisi(): User
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::Teknisi->value);

    return $user;
}

function resiAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);

    return $user;
}

function resiOrderDikerjakan(User $teknisi): Order
{
    $order = Order::factory()->create([
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::Dikerjakan,
    ]);
    // dev-plan/17: kategori default Cuci AC sekarang wajib 6 foto — tes di
    // file ini soal token resi, bukan foto laporan, jadi pindah kategori.
    $order->orderItems->first()->update(['kategori' => ServiceType::ServiceAc]);
    Attendance::create([
        'user_id' => $teknisi->id,
        'order_id' => $order->id,
        'tanggal' => now()->toDateString(),
        'jam_masuk' => now(),
        'status' => AttendanceStatus::Hadir,
    ]);

    return $order;
}

it('submit laporan selesai menghasilkan token resi unik', function () {
    $teknisi = resiTeknisi();
    $order = resiOrderDikerjakan($teknisi);

    $this->teknisiService->submitLaporan($order, $teknisi, [
        'catatan' => 'Cuci bersih.',
        'materials' => [],
    ]);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Selesai)
        ->and($order->resi_token)->not->toBeNull()
        ->and(Str::length($order->resi_token))->toBe(40);
});

it('token resi dibuat sekali dan idempotent', function () {
    $teknisi = resiTeknisi();
    $order = resiOrderDikerjakan($teknisi);
    $this->teknisiService->submitLaporan($order, $teknisi, ['catatan' => 'Selesai.']);

    $pertama = $order->pastikanResiToken();
    $kedua = $order->pastikanResiToken();

    expect($kedua)->toBe($pertama);
});

it('order lunas tanpa laporan teknisi tetap mendapat token resi', function () {
    $admin = resiAdmin();
    $teknisi = resiTeknisi();
    $order = Order::factory()->create([
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::Dikerjakan,
    ]);

    $this->paymentService->recordPayment($order, PaymentMethod::Cash, $order->total(), $admin);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Selesai)
        ->and($order->resi_token)->not->toBeNull();
});

it('order follow-up yang belum selesai tidak mendapat token', function () {
    $teknisi = resiTeknisi();
    $order = resiOrderDikerjakan($teknisi);

    $this->teknisiService->submitLaporan($order, $teknisi, [
        'catatan' => 'Sparepart kurang.',
        'materials' => [],
        'butuh_followup' => true,
    ]);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::ButuhFollowup)
        ->and($order->resi_token)->toBeNull();
});
