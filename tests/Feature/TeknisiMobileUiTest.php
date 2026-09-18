<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Livewire\Teknisi\JadwalHariIni;
use App\Livewire\Teknisi\OrderDetail;
use App\Models\Order;
use App\Models\StockItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->teknisi = User::factory()->create();
    $this->teknisi->assignRole(RoleName::Teknisi->value);
    $this->lainTeknisi = User::factory()->create();
    $this->lainTeknisi->assignRole(RoleName::Teknisi->value);
});

it('guest diarahkan ke login saat akses /teknisi', function () {
    $this->get('/teknisi')->assertRedirect('/login');
});

it('backoffice role (admin) tidak boleh akses /teknisi (403)', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    $this->actingAs($admin)->get('/teknisi')->assertForbidden();
});

it('teknisi melihat jadwal hari ini hanya order miliknya', function () {
    $order = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Terjadwal]);
    $bukanOrderSaya = Order::factory()->create(['teknisi_id' => $this->lainTeknisi->id, 'status' => OrderStatus::Terjadwal]);

    Livewire::actingAs($this->teknisi)
        ->test(JadwalHariIni::class)
        ->assertSee($order->customer->nama)
        ->assertDontSee($bukanOrderSaya->customer->nama);
});

it('order milik teknisi lain -> 403 saat buka detail', function () {
    $order = Order::factory()->create(['teknisi_id' => $this->lainTeknisi->id, 'status' => OrderStatus::Terjadwal]);

    $this->actingAs($this->teknisi)->get('/teknisi/order/'.$order->id)->assertForbidden();
});

it('slider berangkat mengubah status order jadi menuju_lokasi', function () {
    $order = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Terjadwal]);

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->call('berangkat');

    expect($order->fresh()->status)->toBe(OrderStatus::MenujuLokasi);
});

it('check-in mengubah status jadi dikerjakan dan mencatat attendance', function () {
    Storage::fake('public');
    $order = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::MenujuLokasi]);

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        // dev-plan/15 B48: check-in job-site PERTAMA hari itu wajib foto titik pertama (Games 2).
        ->set('fotoTitikPertama', UploadedFile::fake()->image('titik-pertama.jpg'))
        ->call('checkIn');

    expect($order->fresh()->status)->toBe(OrderStatus::Dikerjakan);
    expect($order->attendances()->count())->toBe(1);
});

it('submit laporan lengkap dengan material dan foto -> order selesai, stok berkurang', function () {
    Storage::fake('public');
    $order = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Dikerjakan]);
    // dev-plan/17: kategori default Cuci AC sekarang wajib 6 foto per-kategori —
    // tes ini pakai foto generik sebelum/sesudah, jadi pindah ke kategori belum wajib.
    $order->orderItems->first()->update(['kategori' => ServiceType::ServiceAc]);
    $stockItem = StockItem::factory()->create(['stok_saat_ini' => 5]);

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->set('catatan', 'Pekerjaan selesai dengan baik.')
        ->call('tambahMaterial')
        ->set('materials.0.stock_item_id', $stockItem->id)
        ->set('materials.0.jumlah', 2)
        ->set('fotoSebelum', UploadedFile::fake()->image('sebelum.jpg'))
        ->set('fotoSesudah', UploadedFile::fake()->image('sesudah.jpg'))
        ->call('submitLaporan');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Selesai);
    expect($stockItem->fresh()->stok_saat_ini)->toBe(3);
    expect($order->workReports()->count())->toBe(1);
    Storage::disk('public')->assertExists($order->workReports()->first()->foto_sebelum);
});

it('laporan dengan catatan kosong ditolak (flash error, bukan crash)', function () {
    $order = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Dikerjakan]);

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->set('catatan', '')
        ->call('submitLaporan')
        ->assertHasErrors('catatan');

    expect($order->fresh()->status)->toBe(OrderStatus::Dikerjakan);
});

it('checkIn wajib lewat menuju_lokasi -- langsung dari terjadwal ditolak dengan flash error', function () {
    $order = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Terjadwal]);

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->call('checkIn');

    expect($order->fresh()->status)->toBe(OrderStatus::Terjadwal);
});

it('stepper +/- mengubah jumlah material, tidak boleh kurang dari 1', function () {
    $order = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Dikerjakan]);

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->call('tambahMaterial')
        ->assertSet('materials.0.jumlah', 1)
        ->call('incMaterial', 0)
        ->assertSet('materials.0.jumlah', 2)
        ->call('decMaterial', 0)
        ->call('decMaterial', 0)
        ->assertSet('materials.0.jumlah', 1);
});
