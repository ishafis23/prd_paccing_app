<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentChannelType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Livewire\Teknisi\OrderDetail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentChannel;
use App\Models\User;
use App\Models\WorkReport;
use App\Services\PaymentChannelService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
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

    $this->mkChannelQris = function (User $by, array $overrides = []): PaymentChannel {
        return app(PaymentChannelService::class)->create(array_merge([
            'nama' => 'QRIS Paccing',
            'jenis' => PaymentChannelType::Qris->value,
            'atas_nama' => 'Paccing Official',
        ], $overrides), $by);
    };
});

it('detail order selesai belum lunas menampilkan ringkasan bayar, channel aktif, dan opsi metode', function () {
    $teknisi = ($this->mkTeknisi)();
    $admin = ($this->mkAdmin)();
    ($this->mkChannelQris)($admin);
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->assertSee('Info Pembayaran')
        ->assertSee('Belum bayar')
        ->assertSee('QRIS Paccing')
        ->assertSee('Metode yang dipilih customer')
        ->assertSee('Tunai')
        ->assertSee('Transfer')
        ->assertSee('E-Wallet')
        ->assertSee('Bayar langsung ke teknisi yang bertugas.');
});

it('pilihMetode mencatat metode qris ke orders.metode_dipilih (B13b)', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->call('pilihMetode', 'qris')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'metode_dipilih' => PaymentMethod::Qris->value,
    ]);
});

it('pilihMetode dengan null menghapus tanda metode', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai, [
        'metode_dipilih' => PaymentMethod::Cash->value,
    ]);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->call('pilihMetode', null);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'metode_dipilih' => null,
    ]);
});

it('pilihMetode dengan nilai tidak dikenal ditolak tanpa mengubah data', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->call('pilihMetode', 'dana')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'metode_dipilih' => null,
    ]);
});

it('teknisi bukan PIC tidak bisa menandai metode — akses ditolak dan data tidak berubah', function () {
    $pic = ($this->mkTeknisi)();
    $lain = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($pic, OrderStatus::Selesai, [
        'metode_dipilih' => PaymentMethod::Cash->value,
    ]);

    $this->actingAs($lain)->get('/teknisi/order/'.$order->id)->assertForbidden();

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'metode_dipilih' => PaymentMethod::Cash->value,
    ]);
});

it('order lunas hanya menampilkan badge Lunas, tanpa pilihan metode/channel/tunai', function () {
    $teknisi = ($this->mkTeknisi)();
    $admin = ($this->mkAdmin)();
    ($this->mkChannelQris)($admin);
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai);

    Payment::create([
        'order_id' => $order->id,
        'metode' => PaymentMethod::Qris->value,
        'status' => PaymentStatus::Lunas->value,
        'total_tagihan' => $order->total(),
        'jumlah_dibayar' => $order->total(),
        'tanggal_bayar' => now()->toDateString(),
        'dicatat_oleh' => $admin->id,
    ]);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->assertSee('Info Pembayaran')
        ->assertSee('Lunas')
        ->assertDontSee('Metode yang dipilih customer')
        ->assertDontSee('QRIS Paccing')
        ->assertDontSee('Bayar langsung ke teknisi yang bertugas.');
});

it('order selesai dengan resi token menampilkan blok Bagikan Resi beserta link publik', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai, [
        'resi_token' => 'tokenresiabc123',
    ]);

    $urlResi = route('resi.show', [$order->id, $order->resi_token]);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->assertSee('Bagikan Resi')
        ->assertSee($urlResi);
});

it('galeri foto sebelum/sesudah tampil dari work report terakhir', function () {
    Storage::fake('public');
    $teknisi = ($this->mkTeknisi)();
    $order = ($this->mkOrder)($teknisi, OrderStatus::Selesai);

    $report = WorkReport::factory()->create([
        'order_id' => $order->id,
        'teknisi_id' => $teknisi->id,
        'catatan_pengerjaan' => 'Selesai bersih.',
        'foto_sebelum' => UploadedFile::fake()->image('sebelum.jpg')->store('work-reports', 'public'),
        'foto_sesudah' => UploadedFile::fake()->image('sesudah.jpg')->store('work-reports', 'public'),
    ]);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->assertSee('Foto Pengerjaan')
        ->assertSee('Foto sebelum pengerjaan')
        ->assertSee('Foto sesudah pengerjaan')
        ->assertSee(asset('storage/'.$report->foto_sebelum))
        ->assertSee(asset('storage/'.$report->foto_sesudah));
});
