<?php

use App\Enums\CashHandoverStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\CashHandover;
use App\Models\Order;
use App\Models\User;
use App\Services\CashHandoverService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
});

function handoverTeknisi(): User
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::Teknisi->value);
    return $user;
}

function handoverOrder(User $teknisi, bool $tunai = true): Order
{
    return Order::factory()->create(['teknisi_id' => $teknisi->id, 'metode_dipilih' => $tunai ? PaymentMethod::Cash : PaymentMethod::Transfer, 'metode_dipilih_pada' => now()]);
}

function dataSetoran(): array
{
    Storage::disk('public')->put('setoran-tunai/bukti.jpg', 'bukti');
    return ['nominal' => 10000, 'diserahkan_ke' => 'Admin Kantor', 'tanggal_setor' => today()->toDateString(), 'foto_path' => 'setoran-tunai/bukti.jpg'];
}

it('catat menolak order bukan milik teknisi, non tunai, nominal nol, dan tanpa foto', function () {
    $teknisi = handoverTeknisi();
    $lain = handoverTeknisi();
    $service = app(CashHandoverService::class);
    expect(fn () => $service->catat(handoverOrder($lain), $teknisi, dataSetoran()))->toThrow(AuthorizationException::class);
    expect(fn () => $service->catat(handoverOrder($teknisi, false), $teknisi, dataSetoran()))->toThrow(BusinessRuleException::class);
    expect(fn () => $service->catat(handoverOrder($teknisi), $teknisi, array_merge(dataSetoran(), ['nominal' => 0])))->toThrow(BusinessRuleException::class);
    expect(fn () => $service->catat(handoverOrder($teknisi), $teknisi, array_merge(dataSetoran(), ['foto_path' => 'tidak-ada.jpg'])))->toThrow(BusinessRuleException::class);
});

it('catat sukses menghapus kewajiban aktif dan laporan kedua ditolak', function () {
    $teknisi = handoverTeknisi();
    $order = handoverOrder($teknisi);
    $service = app(CashHandoverService::class);
    $handover = $service->catat($order, $teknisi, dataSetoran());

    expect($handover->status)->toBe(CashHandoverStatus::Menunggu)
        ->and($service->kewajiban($teknisi)->pluck('id')->all())->not->toContain($order->id);
    expect(fn () => $service->catat($order, $teknisi, dataSetoran()))->toThrow(BusinessRuleException::class);
});

it('admin dapat menerima atau menolak, penolakan mengembalikan kewajiban', function () {
    $teknisi = handoverTeknisi();
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);
    $service = app(CashHandoverService::class);
    $diterima = $service->verifikasi($service->catat(handoverOrder($teknisi), $teknisi, dataSetoran()), $admin, true);
    expect($diterima->status)->toBe(CashHandoverStatus::Diterima)->and($diterima->diterima_oleh)->toBe($admin->id)->and($diterima->diterima_pada)->not->toBeNull();
    $orderDitolak = handoverOrder($teknisi);
    $ditolak = $service->verifikasi($service->catat($orderDitolak, $teknisi, dataSetoran()), $admin, false);
    expect($ditolak->status)->toBe(CashHandoverStatus::Ditolak)->and($service->kewajiban($teknisi)->pluck('id')->all())->toContain($orderDitolak->id);
    expect(fn () => $service->verifikasi($ditolak, $teknisi, true))->toThrow(AuthorizationException::class);
});

it('portal setoran dapat dibuka teknisi', function () {
    $teknisi = handoverTeknisi();
    $this->actingAs($teknisi)->get(route('teknisi.setoran'))->assertOk()->assertSee('Setoran Tunai');
});

it('order tunai lama tanpa cap metode tidak mengunci portal (hari deploy)', function () {
    $teknisi = handoverTeknisi();
    $lama = Order::factory()->create([
        'teknisi_id' => $teknisi->id,
        'metode_dipilih' => PaymentMethod::Cash,
        'metode_dipilih_pada' => null,
        'tanggal_jadwal' => now()->subMonth(),
    ]);
    $service = app(CashHandoverService::class);

    expect($service->kewajiban($teknisi)->pluck('id')->all())->not->toContain($lama->id)
        ->and($service->telat($teknisi)->pluck('id')->all())->not->toContain($lama->id);
});
