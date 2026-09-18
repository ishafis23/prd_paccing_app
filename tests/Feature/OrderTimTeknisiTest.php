<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Exceptions\BusinessRuleException;
use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderTechnician;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\OrderService;
use App\Services\StockService;
use App\Services\TeknisiService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->orderService = new OrderService;
    $this->teknisiService = new TeknisiService(new StockService);

    $this->mkUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };
});

function timOrderTerjadwal(User $pic, ?User $anggota = null): Order
{
    $order = Order::factory()->create([
        'teknisi_id' => $pic->id,
        'status' => OrderStatus::Terjadwal,
    ]);

    OrderTechnician::firstOrCreate(['order_id' => $order->id, 'teknisi_id' => $pic->id]);

    if ($anggota !== null) {
        OrderTechnician::firstOrCreate(['order_id' => $order->id, 'teknisi_id' => $anggota->id]);
    }

    return $order;
}

it('order yang dibuat langsung dengan teknisi: PIC + anggota tim tercatat', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $teknisi = ($this->mkUser)(RoleName::Teknisi->value);
    $order = $this->orderService->createOrder([
        'customer_id' => Customer::factory()->create()->id,
        'service_catalog_id' => ServiceCatalog::factory()->create()->id,
        'teknisi_id' => $teknisi->id,
        'jumlah_unit' => 1,
    ], $admin);

    expect($order->teknisi_id)->toBe($teknisi->id)
        ->and(OrderTechnician::query()->where('order_id', $order->id)->count())->toBe(1);
});

it('assign teknisi kedua kalinya tidak menduplikasi baris tim', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $teknisi = ($this->mkUser)(RoleName::Teknisi->value);
    $order = Order::factory()->create(['status' => OrderStatus::Baru]);

    $this->orderService->assignTechnician($order, $teknisi, $admin);
    $this->orderService->assignTechnician($order, $teknisi, $admin);

    expect(OrderTechnician::query()->where('order_id', $order->id)->where('teknisi_id', $teknisi->id)->count())->toBe(1);
});

it('admin dapat menambah anggota tim; PIC tetap pemegang pertama', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $pic = ($this->mkUser)(RoleName::Teknisi->value);
    $anggota = ($this->mkUser)(RoleName::Teknisi->value);
    $order = timOrderTerjadwal($pic);

    $updated = $this->orderService->tambahTeknisi($order, $anggota, $admin);

    expect($updated->teknisi_id)->toBe($pic->id)
        ->and($updated->orderTechnicians()->count())->toBe(2);
});

it('duplikat anggota (termasuk PIC) ditolak', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $pic = ($this->mkUser)(RoleName::Teknisi->value);
    $anggota = ($this->mkUser)(RoleName::Teknisi->value);
    $order = timOrderTerjadwal($pic, $anggota);

    expect(fn () => $this->orderService->tambahTeknisi($order, $pic, $admin))
        ->toThrow(BusinessRuleException::class, 'sudah menjadi anggota');
    expect(fn () => $this->orderService->tambahTeknisi($order, $anggota, $admin))
        ->toThrow(BusinessRuleException::class, 'sudah menjadi anggota');
});

it('tambah anggota saat order selesai ditolak; oleh finance juga ditolak', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $finance = ($this->mkUser)(RoleName::Finance->value);
    $pic = ($this->mkUser)(RoleName::Teknisi->value);
    $anggota = ($this->mkUser)(RoleName::Teknisi->value);
    $selesai = Order::factory()->create(['teknisi_id' => $pic->id, 'status' => OrderStatus::Selesai]);
    $aktif = timOrderTerjadwal($pic);

    expect(fn () => $this->orderService->tambahTeknisi($selesai, $anggota, $admin))
        ->toThrow(BusinessRuleException::class, 'selesai/batal');
    expect(fn () => $this->orderService->tambahTeknisi($aktif, $anggota, $finance))
        ->toThrow(AuthorizationException::class);
});

it('anggota tim (bukan PIC) dapat berangkat dan check-in atas namanya sendiri', function () {
    $pic = ($this->mkUser)(RoleName::Teknisi->value);
    $anggota = ($this->mkUser)(RoleName::Teknisi->value);
    $order = timOrderTerjadwal($pic, $anggota);

    $berangkat = $this->teknisiService->berangkat($order, $anggota);
    expect($berangkat->status)->toBe(OrderStatus::MenujuLokasi);

    $attendance = $this->teknisiService->checkIn($order, $anggota);

    expect($attendance->user_id)->toBe($anggota->id)
        ->and($order->fresh()->status)->toBe(OrderStatus::Dikerjakan);
});

it('teknisi di luar tim tetap ditolak', function () {
    $pic = ($this->mkUser)(RoleName::Teknisi->value);
    $anggota = ($this->mkUser)(RoleName::Teknisi->value);
    $luar = ($this->mkUser)(RoleName::Teknisi->value);
    $order = timOrderTerjadwal($pic, $anggota);

    $this->teknisiService->berangkat($order, $luar);
})->throws(AuthorizationException::class);

it('laporan oleh salah satu anggota menutup attendance semua yang hadir dan order tercatat utk tiap anggota', function () {
    $pic = ($this->mkUser)(RoleName::Teknisi->value);
    $anggota = ($this->mkUser)(RoleName::Teknisi->value);
    $order = timOrderTerjadwal($pic, $anggota);
    $order->orderItems->first()->update(['kategori' => ServiceType::ServiceAc]);

    $this->teknisiService->berangkat($order, $anggota);
    $this->teknisiService->checkIn($order, $anggota); // attendance atas nama anggota

    $report = $this->teknisiService->submitLaporan($order, $pic, [
        'catatan' => 'Pengerjaan tim selesai.',
        'materials' => [],
    ]);

    $order->refresh();
    $attendance = Attendance::query()->where('order_id', $order->id)->where('user_id', $anggota->id)->first();

    expect($report->teknisi_id)->toBe($pic->id)
        ->and($order->status)->toBe(OrderStatus::Selesai)
        ->and($order->resi_token)->not->toBeNull()
        ->and($attendance->jam_keluar)->not->toBeNull()
        ->and(Order::query()->untukTeknisi($pic->id)->where('status', OrderStatus::Selesai->value)->count())->toBe(1)
        ->and(Order::query()->untukTeknisi($anggota->id)->where('status', OrderStatus::Selesai->value)->count())->toBe(1);
});

it('anggota tim melihat order di jadwal & riwayat portalnya (HTTP)', function () {
    $pic = ($this->mkUser)(RoleName::Teknisi->value);
    $anggota = ($this->mkUser)(RoleName::Teknisi->value);
    $customer = Customer::factory()->create(['nama' => 'Pelanggan Tim Uji']);
    $order = timOrderTerjadwal($pic, $anggota);
    $order->update(['customer_id' => $customer->id]);
    $order->orderItems->first()->update(['kategori' => ServiceType::ServiceAc]);

    // Anggota (bukan PIC) melihat order di Jadwal Hari Ini.
    $this->actingAs($anggota)->get('/teknisi')
        ->assertSuccessful()
        ->assertSee('Pelanggan Tim Uji');

    // Selesaikan, lalu cek riwayat anggota.
    $this->teknisiService->berangkat($order, $anggota);
    $this->teknisiService->checkIn($order, $anggota);
    $this->teknisiService->submitLaporan($order, $anggota, ['catatan' => 'Selesai.', 'materials' => []]);

    $this->actingAs($anggota)->get('/teknisi/riwayat')
        ->assertSuccessful()
        ->assertSee('Pelanggan Tim Uji');
});
