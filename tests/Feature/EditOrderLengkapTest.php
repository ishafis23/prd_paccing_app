<?php

use App\Enums\AttendanceStatus;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Attendance;
use App\Models\Order;
use App\Models\OrderTechnician;
use App\Models\Titik;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/**
 * Edit Order lengkap (dev-plan/25) — ganti PIC + atur pendamping + ubah titik,
 * otoritas Admin/Owner, terkunci bila order selesai/batal.
 */
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
});

it('ubahPenugasan mengganti PIC & menyinkronkan pendamping (tambah & hapus)', function () {
    $admin = ($this->mkAdmin)();
    $picLama = ($this->mkTeknisi)();
    $picBaru = ($this->mkTeknisi)();
    $pendampingBaru = ($this->mkTeknisi)();
    $pendampingLama = ($this->mkTeknisi)();

    $order = Order::factory()->create(['teknisi_id' => $picLama->id, 'status' => OrderStatus::Dikerjakan]);
    OrderTechnician::firstOrCreate(['order_id' => $order->id, 'teknisi_id' => $picLama->id]);
    OrderTechnician::firstOrCreate(['order_id' => $order->id, 'teknisi_id' => $pendampingLama->id]);

    $hasil = app(OrderService::class)->ubahPenugasan($order, $picBaru, [$pendampingBaru->id], $admin);

    expect($hasil->teknisi_id)->toBe($picBaru->id);

    $timIds = $hasil->orderTechnicians()->pluck('teknisi_id')->all();
    expect($timIds)->toContain($picBaru->id, $pendampingBaru->id);
    expect($timIds)->not->toContain($picLama->id, $pendampingLama->id);
});

it('ubahPenugasan mengabaikan pendamping yang sama dengan PIC', function () {
    $admin = ($this->mkAdmin)();
    $pic = ($this->mkTeknisi)();
    $pendamping = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $pic->id, 'status' => OrderStatus::Terjadwal]);

    app(OrderService::class)->ubahPenugasan($order, $pic, [$pic->id, $pendamping->id], $admin);

    $timIds = $order->fresh()->orderTechnicians()->pluck('teknisi_id')->all();
    expect(array_count_values($timIds)[$pic->id])->toBe(1);
    expect($timIds)->toContain($pendamping->id);
});

it('ubahPenugasan menutup attendance terbuka PIC lama & mencatat catatan_admin', function () {
    $admin = ($this->mkAdmin)();
    $picLama = ($this->mkTeknisi)();
    $picBaru = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $picLama->id, 'status' => OrderStatus::Dikerjakan]);

    $attendance = Attendance::create([
        'user_id' => $picLama->id,
        'order_id' => $order->id,
        'tanggal' => now()->toDateString(),
        'jam_masuk' => now()->subHour(),
        'jam_keluar' => null,
        'status' => AttendanceStatus::Hadir,
    ]);

    $hasil = app(OrderService::class)->ubahPenugasan($order, $picBaru, [], $admin, 'rotasi tim');

    expect($attendance->fresh()->jam_keluar)->not->toBeNull();
    expect($hasil->catatan_admin)->toContain('[EDIT ORDER]')
        ->and($hasil->catatan_admin)->toContain($picBaru->name)
        ->and($hasil->catatan_admin)->toContain('rotasi tim');
});

it('ubahTitik mengubah titik_id + jam_jadwal, dan bisa dikosongkan', function () {
    $admin = ($this->mkAdmin)();
    $order = Order::factory()->create(['status' => OrderStatus::Terjadwal]);
    $titik = Titik::factory()->create(['nama' => 'Titik 1', 'jam' => '08:15', 'aktif' => true]);

    $hasil = app(OrderService::class)->ubahTitik($order, $titik->id, $admin);
    expect($hasil->titik_id)->toBe($titik->id);
    expect($hasil->jam_jadwal)->toBe($titik->fresh()->jam);

    $kosong = app(OrderService::class)->ubahTitik($hasil, null, $admin);
    expect($kosong->titik_id)->toBeNull();
});

it('ubahPenugasan & ubahTitik menolak order selesai/batal', function (OrderStatus $status) {
    $admin = ($this->mkAdmin)();
    $pic = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $pic->id, 'status' => $status]);
    $titik = Titik::factory()->create();

    expect(fn () => app(OrderService::class)->ubahPenugasan($order, $pic, [], $admin))
        ->toThrow(BusinessRuleException::class, 'selesai/batal');
    expect(fn () => app(OrderService::class)->ubahTitik($order, $titik->id, $admin))
        ->toThrow(BusinessRuleException::class, 'selesai/batal');
})->with([
    [OrderStatus::Selesai],
    [OrderStatus::Batal],
]);

it('ubahPenugasan & ubahTitik hanya untuk admin/owner', function () {
    $pic = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $pic->id, 'status' => OrderStatus::Terjadwal]);
    $titik = Titik::factory()->create();
    $finance = User::factory()->create();
    $finance->assignRole(RoleName::Finance->value);

    expect(fn () => app(OrderService::class)->ubahPenugasan($order, $pic, [], $finance))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(OrderService::class)->ubahTitik($order, $titik->id, $finance))
        ->toThrow(AuthorizationException::class);
});

it('aksi Edit Order tampil utk admin pada order belum selesai & tersembunyi utk order selesai', function () {
    $admin = ($this->mkAdmin)();
    $pic = ($this->mkTeknisi)();
    $aktif = Order::factory()->create(['teknisi_id' => $pic->id, 'status' => OrderStatus::Terjadwal]);
    $selesai = Order::factory()->create(['teknisi_id' => $pic->id, 'status' => OrderStatus::Selesai]);

    Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->assertTableActionVisible('editOrder', $aktif)
        ->assertTableActionHidden('editOrder', $selesai);
});

it('aksi Edit Order via tabel admin mengganti PIC, pendamping, dan titik', function () {
    $admin = ($this->mkAdmin)();
    $picLama = ($this->mkTeknisi)();
    $picBaru = ($this->mkTeknisi)();
    $pendamping = ($this->mkTeknisi)();
    $titik = Titik::factory()->create(['jam' => '09:30']);
    $order = Order::factory()->create(['teknisi_id' => $picLama->id, 'status' => OrderStatus::Terjadwal]);

    Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->callTableAction('editOrder', $order, data: [
            'teknisi_id' => $picBaru->id,
            'pendamping_ids' => [$pendamping->id],
            'titik_id' => $titik->id,
        ])
        ->assertNotified();

    $fresh = $order->fresh();
    expect($fresh->teknisi_id)->toBe($picBaru->id);
    expect($fresh->titik_id)->toBe($titik->id);
    expect($fresh->orderTechnicians()->pluck('teknisi_id')->all())->toContain($picBaru->id, $pendamping->id);
});
