<?php

use App\Enums\AttendanceStatus;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Teknisi\OrderDetail;
use App\Models\Attendance;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\TeknisiService;
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

    $this->mkOrder = function (User $teknisi, OrderStatus $status, array $ekstra = []): Order {
        return Order::factory()->create(array_merge([
            'teknisi_id' => $teknisi->id,
            'status' => $status,
        ], $ekstra));
    };
});

it('tandaiKendala mengubah status jadi terkendala & mencatat alasan', function (OrderStatus $dari) {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, $dari);

    $hasil = app(TeknisiService::class)->tandaiKendala($order, $teknisi, 'Customer tidak ada di lokasi');

    expect($hasil->status)->toBe(OrderStatus::Terkendala);
    expect($hasil->alasan_kendala)->toBe('Customer tidak ada di lokasi');
    expect($hasil->catatan_admin)->toContain('[KENDALA] Customer tidak ada di lokasi');
})->with([
    'terjadwal' => [OrderStatus::Terjadwal],
    'menuju lokasi' => [OrderStatus::MenujuLokasi],
    'dikerjakan' => [OrderStatus::Dikerjakan],
]);

it('tandaiKendala menolak alasan kosong', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::MenujuLokasi);

    app(TeknisiService::class)->tandaiKendala($order, $teknisi, '   ');
})->throws(BusinessRuleException::class, 'wajib diisi');

it('tandaiKendala menolak dari status selesai/batal', function (OrderStatus $status) {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, $status);

    app(TeknisiService::class)->tandaiKendala($order, $teknisi, 'alasan apapun');
})->with([
    [OrderStatus::Selesai],
    [OrderStatus::Batal],
])->throws(BusinessRuleException::class);

it('tandaiKendala menolak teknisi yang bukan anggota tim order', function () {
    $pemilik = ($this->mkTeknisi)();
    $lain = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($pemilik, OrderStatus::MenujuLokasi);

    app(TeknisiService::class)->tandaiKendala($order, $lain, 'alasan');
})->throws(AuthorizationException::class);

it('tandaiKendala menutup attendance terbuka saat order sedang dikerjakan', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Dikerjakan);
    $attendance = Attendance::create([
        'user_id' => $teknisi->id,
        'order_id' => $order->id,
        'tanggal' => now()->toDateString(),
        'jam_masuk' => now()->subHour(),
        'jam_keluar' => null,
        'status' => AttendanceStatus::Hadir,
    ]);

    app(TeknisiService::class)->tandaiKendala($order, $teknisi, 'batal mendadak');

    expect($attendance->fresh()->jam_keluar)->not->toBeNull();
});

it('reschedule mengembalikan order ke terjadwal dgn jadwal baru & bersihkan alasan kendala', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Terkendala, ['alasan_kendala' => 'customer tidak jadi']);

    $hasil = app(OrderService::class)->reschedule($order, $admin, '2026-10-05', '10:00');

    expect($hasil->status)->toBe(OrderStatus::Terjadwal);
    expect($hasil->tanggal_jadwal->toDateString())->toBe('2026-10-05');
    expect($hasil->jam_jadwal)->toBe('10:00');
    expect($hasil->alasan_kendala)->toBeNull();
    expect($hasil->catatan_admin)->toContain('[JADWAL ULANG] 2026-10-05 10:00');
});

it('reschedule menolak order yang bukan berstatus terkendala', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Terjadwal);

    app(OrderService::class)->reschedule($order, $admin, '2026-10-05');
})->throws(BusinessRuleException::class, 'terkendala');

it('reschedule menolak bukan admin/owner', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Terkendala);

    app(OrderService::class)->reschedule($order, $teknisi, '2026-10-05');
})->throws(AuthorizationException::class);

it('order terkendala tetap bisa dibatalkan admin', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Terkendala);

    $hasil = app(OrderService::class)->cancel($order, $admin, 'tidak jadi dikerjakan lagi');

    expect($hasil->status)->toBe(OrderStatus::Batal);
});

it('tombol Terkendala/Gagal muncul saat order aktif, tidak muncul saat selesai', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::MenujuLokasi);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->assertSee('Terkendala / Gagal');

    $selesai = ($this->mkOrder)($teknisi, OrderStatus::Selesai);
    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $selesai])
        ->assertDontSee('Terkendala / Gagal');
});

it('kartu Order Terkendala tampil dgn alasan saat status terkendala', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Terkendala, ['alasan_kendala' => 'customer reschedule sendiri']);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->assertSee('Order Terkendala')
        ->assertSee('customer reschedule sendiri')
        ->assertDontSee('Terkendala / Gagal'); // sudah terkendala, tombol tidak perlu muncul lagi
});

it('aksi tandaiKendala via Livewire mengubah status & flash pesan status', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::MenujuLokasi);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->set('alasanKendala', 'customer tidak jadi')
        ->call('tandaiKendala')
        ->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Terkendala);
    expect($order->fresh()->alasan_kendala)->toBe('customer tidak jadi');
});

it('aksi Jadwalkan Ulang di admin table hanya muncul utk order terkendala', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $terkendala = ($this->mkOrder)($teknisi, OrderStatus::Terkendala, ['alasan_kendala' => 'customer tidak ada']);
    $terjadwal = ($this->mkOrder)($teknisi, OrderStatus::Terjadwal);

    Livewire::actingAs($admin)
        ->test(\App\Filament\Resources\OrderResource\Pages\ListOrders::class)
        ->assertTableActionVisible('jadwalkanUlang', $terkendala)
        ->assertTableActionHidden('jadwalkanUlang', $terjadwal);
});

it('aksi Jadwalkan Ulang via admin table mengembalikan order ke terjadwal', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Terkendala, ['alasan_kendala' => 'customer tidak ada']);

    Livewire::actingAs($admin)
        ->test(\App\Filament\Resources\OrderResource\Pages\ListOrders::class)
        ->callTableAction('jadwalkanUlang', $order, data: [
            'tanggal_jadwal' => '2026-10-06',
            'jam_jadwal' => '13:00',
        ])
        ->assertNotified();

    $fresh = $order->fresh();
    expect($fresh->status)->toBe(OrderStatus::Terjadwal);
    expect($fresh->tanggal_jadwal->toDateString())->toBe('2026-10-06');
    expect($fresh->alasan_kendala)->toBeNull();
});
