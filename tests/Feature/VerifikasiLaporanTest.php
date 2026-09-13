<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkReport;
use App\Services\OrderService;
use App\Services\PaymentService;
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

    $this->mkAdmin = function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    };

    $this->mkFinance = function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Finance->value);

        return $user;
    };

    $this->mkLaporan = function (Order $order, User $teknisi, array $ekstra = []): WorkReport {
        return WorkReport::factory()->create(array_merge([
            'order_id' => $order->id,
            'teknisi_id' => $teknisi->id,
        ], $ekstra));
    };
});

it('verifikasiLaporan menandai diverifikasi_pada & diverifikasi_oleh', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Selesai]);
    $laporan = ($this->mkLaporan)($order, $teknisi);

    $hasil = app(OrderService::class)->verifikasiLaporan($laporan, $admin);

    expect($hasil->sudahDiverifikasi())->toBeTrue();
    expect($hasil->diverifikasi_oleh)->toBe($admin->id);
});

it('verifikasiLaporan menolak laporan yang sudah diverifikasi', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Selesai]);
    $laporan = ($this->mkLaporan)($order, $teknisi, ['diverifikasi_pada' => now(), 'diverifikasi_oleh' => $admin->id]);

    app(OrderService::class)->verifikasiLaporan($laporan, $admin);
})->throws(BusinessRuleException::class, 'sudah diverifikasi');

it('verifikasiLaporan menolak bukan admin/owner', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Selesai]);
    $laporan = ($this->mkLaporan)($order, $teknisi);

    app(OrderService::class)->verifikasiLaporan($laporan, $teknisi);
})->throws(AuthorizationException::class);

it('recordPayment menolak pelunasan sebelum laporan terakhir diverifikasi', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Selesai]);
    ($this->mkLaporan)($order, $teknisi);

    app(PaymentService::class)->recordPayment($order, PaymentMethod::Cash, $order->total(), $admin);
})->throws(BusinessRuleException::class, 'Verifikasi laporan');

it('recordPayment berhasil melunaskan setelah laporan diverifikasi', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Selesai]);
    $laporan = ($this->mkLaporan)($order, $teknisi);

    app(OrderService::class)->verifikasiLaporan($laporan, $admin);

    $payment = app(PaymentService::class)->recordPayment($order, PaymentMethod::Cash, $order->total(), $admin);

    expect($payment->status->value)->toBe('lunas');
});

it('recordPayment tetap boleh DP (belum lunas) walau laporan belum diverifikasi', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Selesai]);
    ($this->mkLaporan)($order, $teknisi);

    $payment = app(PaymentService::class)->recordPayment($order, PaymentMethod::Cash, $order->total() / 2, $admin);

    expect($payment->status->value)->toBe('dp');
});

it('recordPayment tidak terhalang kalau order belum ada laporan sama sekali', function () {
    $admin = ($this->mkAdmin)();
    $order = Order::factory()->create(['status' => OrderStatus::Baru]);

    $payment = app(PaymentService::class)->recordPayment($order, PaymentMethod::Cash, $order->total(), $admin);

    expect($payment->status->value)->toBe('lunas');
});

it('kolom & aksi Verifikasi Laporan tampil di tabel admin sesuai status', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();

    $belumAdaLaporan = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);

    $adaLaporanBelumVerif = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Selesai]);
    ($this->mkLaporan)($adaLaporanBelumVerif, $teknisi);

    $sudahVerif = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Selesai]);
    ($this->mkLaporan)($sudahVerif, $teknisi, ['diverifikasi_pada' => now(), 'diverifikasi_oleh' => $admin->id]);

    Livewire::actingAs($admin)
        ->test(\App\Filament\Resources\OrderResource\Pages\ListOrders::class)
        ->assertTableActionHidden('verifikasiLaporan', $belumAdaLaporan)
        ->assertTableActionVisible('verifikasiLaporan', $adaLaporanBelumVerif)
        ->assertTableActionHidden('verifikasiLaporan', $sudahVerif);
});

it('aksi Verifikasi Laporan via admin table menandai laporan terverifikasi', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Selesai]);
    $laporan = ($this->mkLaporan)($order, $teknisi);

    Livewire::actingAs($admin)
        ->test(\App\Filament\Resources\OrderResource\Pages\ListOrders::class)
        ->callTableAction('verifikasiLaporan', $order)
        ->assertNotified();

    expect($laporan->fresh()->sudahDiverifikasi())->toBeTrue();
});

it('finance tidak melihat aksi Verifikasi Laporan (hanya admin/owner)', function () {
    $finance = ($this->mkFinance)();
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Selesai]);
    ($this->mkLaporan)($order, $teknisi);

    Livewire::actingAs($finance)
        ->test(\App\Filament\Resources\OrderResource\Pages\ListOrders::class)
        ->assertTableActionHidden('verifikasiLaporan', $order);
});
