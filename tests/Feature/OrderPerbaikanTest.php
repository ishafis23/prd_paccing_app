<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Livewire\Teknisi\OrderDetail;
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

it('laporPerbaikan menandai order menunggu konfirmasi tanpa mengubah status', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Dikerjakan);

    $hasil = app(TeknisiService::class)->laporPerbaikan($order, $teknisi, 'Kapasitor lemah, perlu diganti', 75000);

    expect($hasil->status)->toBe(OrderStatus::Dikerjakan);
    expect($hasil->perbaikan_menunggu_konfirmasi)->toBeTrue();
    expect($hasil->perbaikan_catatan)->toBe('Kapasitor lemah, perlu diganti');
    expect((float) $hasil->perbaikan_estimasi_harga)->toBe(75000.0);
    expect($hasil->perbaikan_dilaporkan_oleh)->toBe($teknisi->id);
});

it('laporPerbaikan boleh tanpa estimasi harga', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Dikerjakan);

    $hasil = app(TeknisiService::class)->laporPerbaikan($order, $teknisi, 'Perlu freon tambahan');

    expect($hasil->perbaikan_menunggu_konfirmasi)->toBeTrue();
    expect($hasil->perbaikan_estimasi_harga)->toBeNull();
});

it('laporPerbaikan menolak catatan kosong', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Dikerjakan);

    app(TeknisiService::class)->laporPerbaikan($order, $teknisi, '   ');
})->throws(BusinessRuleException::class, 'wajib diisi');

it('laporPerbaikan menolak selain status dikerjakan', function (OrderStatus $status) {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, $status);

    app(TeknisiService::class)->laporPerbaikan($order, $teknisi, 'alasan');
})->with([
    [OrderStatus::Terjadwal],
    [OrderStatus::MenujuLokasi],
    [OrderStatus::Selesai],
])->throws(BusinessRuleException::class);

it('laporPerbaikan menolak kalau masih ada laporan menunggu konfirmasi', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Dikerjakan);

    app(TeknisiService::class)->laporPerbaikan($order, $teknisi, 'laporan pertama');

    app(TeknisiService::class)->laporPerbaikan($order, $teknisi, 'laporan kedua');
})->throws(BusinessRuleException::class, 'menunggu konfirmasi');

it('laporPerbaikan menolak teknisi yang bukan anggota tim order', function () {
    $pemilik = ($this->mkTeknisi)();
    $lain = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($pemilik, OrderStatus::Dikerjakan);

    app(TeknisiService::class)->laporPerbaikan($order, $lain, 'alasan');
})->throws(AuthorizationException::class);

it('setujuiPerbaikan menambah order_item baru & membersihkan flag', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Dikerjakan);
    app(TeknisiService::class)->laporPerbaikan($order, $teknisi, 'Kapasitor lemah', 75000);

    $totalAwal = $order->fresh()->total();

    $item = app(OrderService::class)->setujuiPerbaikan($order, [
        'nama_layanan' => 'Ganti Kapasitor',
        'kategori' => ServiceType::ServiceAc->value,
        'harga' => 75000,
        'jumlah' => 1,
    ], $admin);

    $fresh = $order->fresh();
    expect($item->nama_layanan)->toBe('Ganti Kapasitor');
    expect($fresh->perbaikan_menunggu_konfirmasi)->toBeFalse();
    expect($fresh->perbaikan_catatan)->toBeNull();
    expect($fresh->perbaikan_dilaporkan_oleh)->toBeNull();
    expect($fresh->total())->toBe($totalAwal + 75000.0);
});

it('setujuiPerbaikan menolak kalau tidak ada laporan menunggu konfirmasi', function () {
    $admin = ($this->mkAdmin)();
    $order = Order::factory()->create(['status' => OrderStatus::Dikerjakan]);

    app(OrderService::class)->setujuiPerbaikan($order, ['nama_layanan' => 'X', 'harga' => 1000], $admin);
})->throws(BusinessRuleException::class, 'Tidak ada laporan perbaikan');

it('setujuiPerbaikan TETAP berhasil walau order sudah selesai/batal sebelum admin sempat menyetujui', function (OrderStatus $statusSetelah) {
    // Client (dev-plan/teknisi/LIST Portal Teknisi.pdf, poin 2): teknisi
    // lapor perbaikan lalu keburu klik "Selesaikan Order" sebelum admin
    // sempat menyetujui — sebelumnya setujuiPerbaikan() ikut ditolak
    // guard Selesai/Batal milik tambahLayanan() biasa ("Order selesai/
    // batal tidak bisa ditambah layanan"), padahal perbaikannya sendiri
    // sudah dilaporkan SAAT order masih aktif.
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Dikerjakan);
    app(TeknisiService::class)->laporPerbaikan($order, $teknisi, 'Kapasitor lemah', 75000);

    $order->status = $statusSetelah;
    $order->save();

    $item = app(OrderService::class)->setujuiPerbaikan($order, [
        'nama_layanan' => 'Ganti Kapasitor',
        'harga' => 75000,
        'jumlah' => 1,
    ], $admin);

    expect($item->nama_layanan)->toBe('Ganti Kapasitor')
        ->and($order->fresh()->perbaikan_menunggu_konfirmasi)->toBeFalse();
})->with([
    'selesai' => [OrderStatus::Selesai],
    'batal' => [OrderStatus::Batal],
]);

it('tambahLayanan (biasa, bukan lewat setujuiPerbaikan) TETAP menolak order selesai/batal — tidak ikut longgar', function (OrderStatus $status) {
    $admin = ($this->mkAdmin)();
    $order = Order::factory()->create(['status' => $status]);

    app(OrderService::class)->tambahLayanan($order, ['nama_layanan' => 'X', 'harga' => 1000], $admin);
})->with([
    'selesai' => [OrderStatus::Selesai],
    'batal' => [OrderStatus::Batal],
])->throws(BusinessRuleException::class, 'Order selesai/batal tidak bisa ditambah layanan.');

it('tolakPerbaikan membersihkan flag tanpa menambah order_item', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Dikerjakan);
    app(TeknisiService::class)->laporPerbaikan($order, $teknisi, 'Kapasitor lemah', 75000);

    $jumlahItemAwal = $order->fresh()->orderItems->count();

    $hasil = app(OrderService::class)->tolakPerbaikan($order, $admin, 'Customer tidak mau bayar tambahan');

    expect($hasil->perbaikan_menunggu_konfirmasi)->toBeFalse();
    expect($hasil->perbaikan_catatan)->toBeNull();
    expect($hasil->orderItems)->toHaveCount($jumlahItemAwal);
    expect($hasil->catatan_admin)->toContain('[PERBAIKAN DITOLAK] Kapasitor lemah');
    expect($hasil->catatan_admin)->toContain('Customer tidak mau bayar tambahan');
});

it('tolakPerbaikan menolak kalau tidak ada laporan menunggu konfirmasi', function () {
    $admin = ($this->mkAdmin)();
    $order = Order::factory()->create(['status' => OrderStatus::Dikerjakan]);

    app(OrderService::class)->tolakPerbaikan($order, $admin);
})->throws(BusinessRuleException::class, 'Tidak ada laporan perbaikan');

it('setujuiPerbaikan & tolakPerbaikan menolak bukan admin/owner', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Dikerjakan);
    app(TeknisiService::class)->laporPerbaikan($order, $teknisi, 'Kapasitor lemah');

    expect(fn () => app(OrderService::class)->setujuiPerbaikan($order, ['nama_layanan' => 'X', 'harga' => 1000], $teknisi))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(OrderService::class)->tolakPerbaikan($order, $teknisi))
        ->toThrow(AuthorizationException::class);
});

it('tombol Ada Perbaikan muncul saat dikerjakan, notice muncul saat menunggu konfirmasi', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Dikerjakan);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->assertSee('Ada Perbaikan')
        ->assertDontSee('Menunggu Konfirmasi Perbaikan');

    app(TeknisiService::class)->laporPerbaikan($order, $teknisi, 'Kapasitor lemah, perlu diganti');

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->assertSee('Menunggu Konfirmasi Perbaikan')
        ->assertSee('Kapasitor lemah, perlu diganti');
});

it('aksi laporPerbaikan via Livewire mengubah flag & flash pesan status', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Dikerjakan);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->set('catatanPerbaikan', 'Perlu ganti freon')
        ->call('laporPerbaikan')
        ->assertOk();

    expect($order->fresh()->perbaikan_menunggu_konfirmasi)->toBeTrue();
    expect($order->fresh()->perbaikan_catatan)->toBe('Perlu ganti freon');
});

it('aksi Setujui/Tolak Perbaikan hanya muncul saat order menunggu konfirmasi', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $menunggu = ($this->mkOrder)($teknisi, OrderStatus::Dikerjakan);
    app(TeknisiService::class)->laporPerbaikan($menunggu, $teknisi, 'Kapasitor lemah');
    $normal = ($this->mkOrder)($teknisi, OrderStatus::Dikerjakan);

    Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->assertTableActionVisible('setujuiPerbaikan', $menunggu)
        ->assertTableActionVisible('tolakPerbaikan', $menunggu)
        ->assertTableActionHidden('setujuiPerbaikan', $normal)
        ->assertTableActionHidden('tolakPerbaikan', $normal);
});

it('aksi Setujui Perbaikan via admin table membuat order_item & membersihkan flag', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Dikerjakan);
    app(TeknisiService::class)->laporPerbaikan($order, $teknisi, 'Kapasitor lemah', 75000);

    Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->callTableAction('setujuiPerbaikan', $order, data: [
            'nama_layanan' => 'Ganti Kapasitor',
            'harga' => 75000,
            'jumlah' => 1,
        ])
        ->assertNotified();

    $fresh = $order->fresh();
    expect($fresh->orderItems)->toHaveCount(2);
    expect($fresh->perbaikan_menunggu_konfirmasi)->toBeFalse();
});

it('aksi Tolak Perbaikan via admin table membersihkan flag tanpa order_item baru', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Dikerjakan);
    app(TeknisiService::class)->laporPerbaikan($order, $teknisi, 'Kapasitor lemah', 75000);
    $jumlahItemAwal = $order->fresh()->orderItems->count();

    Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->callTableAction('tolakPerbaikan', $order, data: ['catatan' => 'Customer tidak setuju'])
        ->assertNotified();

    $fresh = $order->fresh();
    expect($fresh->orderItems)->toHaveCount($jumlahItemAwal);
    expect($fresh->perbaikan_menunggu_konfirmasi)->toBeFalse();
});
