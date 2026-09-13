<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Attendance;
use App\Models\Order;
use App\Models\OrderTechnician;
use App\Models\User;
use App\Services\OrderService;
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
});

it('gantiPic mengganti PIC tanpa mengubah status order', function (OrderStatus $status) {
    $admin = ($this->mkAdmin)();
    $picLama = ($this->mkTeknisi)();
    $picBaru = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $picLama->id, 'status' => $status]);
    OrderTechnician::firstOrCreate(['order_id' => $order->id, 'teknisi_id' => $picLama->id]);

    $hasil = app(OrderService::class)->gantiPic($order, $picBaru, $admin);

    expect($hasil->status)->toBe($status);
    expect($hasil->teknisi_id)->toBe($picBaru->id);
})->with([
    'terjadwal' => [OrderStatus::Terjadwal],
    'menuju lokasi' => [OrderStatus::MenujuLokasi],
    'dikerjakan' => [OrderStatus::Dikerjakan],
    'butuh followup' => [OrderStatus::ButuhFollowup],
    'terkendala' => [OrderStatus::Terkendala],
]);

it('gantiPic melepas PIC lama & mendaftarkan PIC baru sbg anggota tim, anggota lain tidak terganggu', function () {
    $admin = ($this->mkAdmin)();
    $picLama = ($this->mkTeknisi)();
    $picBaru = ($this->mkTeknisi)();
    $anggota = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $picLama->id, 'status' => OrderStatus::Dikerjakan]);
    OrderTechnician::firstOrCreate(['order_id' => $order->id, 'teknisi_id' => $picLama->id]);
    OrderTechnician::firstOrCreate(['order_id' => $order->id, 'teknisi_id' => $anggota->id]);

    app(OrderService::class)->gantiPic($order, $picBaru, $admin);

    $timIds = $order->orderTechnicians()->pluck('teknisi_id')->all();
    expect($timIds)->not->toContain($picLama->id);
    expect($timIds)->toContain($picBaru->id, $anggota->id);
});

it('gantiPic menutup attendance terbuka milik PIC lama', function () {
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
        'status' => \App\Enums\AttendanceStatus::Hadir,
    ]);

    app(OrderService::class)->gantiPic($order, $picBaru, $admin);

    expect($attendance->fresh()->jam_keluar)->not->toBeNull();
});

it('gantiPic mencatat riwayat ke catatan_admin', function () {
    $admin = ($this->mkAdmin)();
    $picLama = ($this->mkTeknisi)();
    $picBaru = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $picLama->id, 'status' => OrderStatus::Terjadwal]);

    $hasil = app(OrderService::class)->gantiPic($order, $picBaru, $admin, 'sakit mendadak');

    expect($hasil->catatan_admin)->toContain('[GANTI PIC]')
        ->and($hasil->catatan_admin)->toContain($picLama->name)
        ->and($hasil->catatan_admin)->toContain($picBaru->name)
        ->and($hasil->catatan_admin)->toContain('sakit mendadak');
});

it('gantiPic menolak order yang belum punya PIC', function () {
    $admin = ($this->mkAdmin)();
    $picBaru = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => null, 'status' => OrderStatus::Baru]);

    app(OrderService::class)->gantiPic($order, $picBaru, $admin);
})->throws(BusinessRuleException::class, 'belum punya PIC');

it('gantiPic menolak kalau teknisi baru sama dgn PIC lama', function () {
    $admin = ($this->mkAdmin)();
    $pic = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $pic->id, 'status' => OrderStatus::Terjadwal]);

    app(OrderService::class)->gantiPic($order, $pic, $admin);
})->throws(BusinessRuleException::class, 'sudah menjadi PIC');

it('gantiPic menolak order selesai/batal', function (OrderStatus $status) {
    $admin = ($this->mkAdmin)();
    $picLama = ($this->mkTeknisi)();
    $picBaru = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $picLama->id, 'status' => $status]);

    app(OrderService::class)->gantiPic($order, $picBaru, $admin);
})->with([
    [OrderStatus::Selesai],
    [OrderStatus::Batal],
])->throws(BusinessRuleException::class, 'selesai/batal');

it('gantiPic menolak bukan admin/owner atau teknisi baru bukan role teknisi', function () {
    $picLama = ($this->mkTeknisi)();
    $picBaru = ($this->mkTeknisi)();
    $finance = User::factory()->create();
    $finance->assignRole(RoleName::Finance->value);
    $order = Order::factory()->create(['teknisi_id' => $picLama->id, 'status' => OrderStatus::Terjadwal]);

    expect(fn () => app(OrderService::class)->gantiPic($order, $picBaru, $finance))
        ->toThrow(AuthorizationException::class);

    $admin = ($this->mkAdmin)();
    expect(fn () => app(OrderService::class)->gantiPic($order, $finance, $admin))
        ->toThrow(AuthorizationException::class);
});

it('aksi Ganti PIC & Assign Teknisi saling eksklusif di tabel admin', function () {
    $admin = ($this->mkAdmin)();
    $picLama = ($this->mkTeknisi)();
    $belumAdaPic = Order::factory()->create(['teknisi_id' => null, 'status' => OrderStatus::Baru]);
    $sudahAdaPic = Order::factory()->create(['teknisi_id' => $picLama->id, 'status' => OrderStatus::Dikerjakan]);

    Livewire::actingAs($admin)
        ->test(\App\Filament\Resources\OrderResource\Pages\ListOrders::class)
        ->assertTableActionVisible('assignTeknisi', $belumAdaPic)
        ->assertTableActionHidden('gantiPic', $belumAdaPic)
        ->assertTableActionHidden('assignTeknisi', $sudahAdaPic)
        ->assertTableActionVisible('gantiPic', $sudahAdaPic);
});

it('aksi Ganti PIC via admin table mengganti teknisi_id tanpa mengubah status', function () {
    $admin = ($this->mkAdmin)();
    $picLama = ($this->mkTeknisi)();
    $picBaru = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $picLama->id, 'status' => OrderStatus::Dikerjakan]);

    Livewire::actingAs($admin)
        ->test(\App\Filament\Resources\OrderResource\Pages\ListOrders::class)
        ->callTableAction('gantiPic', $order, data: [
            'teknisi_id' => $picBaru->id,
            'alasan' => 'berhalangan mendadak',
        ])
        ->assertNotified();

    $fresh = $order->fresh();
    expect($fresh->teknisi_id)->toBe($picBaru->id);
    expect($fresh->status)->toBe(OrderStatus::Dikerjakan);
});
