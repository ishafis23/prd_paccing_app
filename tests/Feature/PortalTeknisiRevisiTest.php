<?php

use App\Enums\DailyAttendanceStatus;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Livewire\Teknisi\JadwalHariIni;
use App\Livewire\Teknisi\OrderDetail;
use App\Models\DailyAttendance;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

/**
 * Revisi portal teknisi sesuai masukan client (dev-plan/revisiteknisi):
 * sekat order kemarin, catatan admin, gating absen, & label check-in pertama.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->teknisi = User::factory()->create();
    $this->teknisi->assignRole(RoleName::Teknisi->value);
});

it('jadwal memisahkan order kemarin belum selesai dari order hari ini', function () {
    $kemarin = Order::factory()->create([
        'teknisi_id' => $this->teknisi->id,
        'status' => OrderStatus::Terkendala,
        'tanggal_jadwal' => now()->subDay()->toDateString(),
    ]);
    $hariIni = Order::factory()->create([
        'teknisi_id' => $this->teknisi->id,
        'status' => OrderStatus::Terjadwal,
        'tanggal_jadwal' => now()->toDateString(),
    ]);

    Livewire::actingAs($this->teknisi)->test(JadwalHariIni::class)
        ->assertSee('Belum Selesai Sebelumnya')
        ->assertSee('Hari Ini & Akan Datang')
        ->assertSee($kemarin->customer->nama)
        ->assertSee($hariIni->customer->nama);
});

it('jadwal diurutkan menurut jam kunjungan & menampilkan label TITIK', function () {
    $titik2 = \App\Models\Titik::create(['nama' => 'Titik 2', 'jam' => '09:30', 'urutan' => 2, 'aktif' => true]);

    // Dibuat lebih dulu tapi jamnya paling akhir; yang diedit belakangan jamnya paling awal.
    $siang = Order::factory()->create([
        'teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Terjadwal,
        'tanggal_jadwal' => now()->toDateString(), 'jam_jadwal' => '13:00',
    ]);
    $pagi = Order::factory()->create([
        'teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Terjadwal,
        'tanggal_jadwal' => now()->toDateString(), 'jam_jadwal' => '09:30', 'titik_id' => $titik2->id,
    ]);
    $siang->touch();

    Livewire::actingAs($this->teknisi)->test(JadwalHariIni::class)
        ->assertSeeInOrder([$pagi->customer->nama, $siang->customer->nama])
        ->assertSee('Titik 2');
});

it('order tidak bisa dibuka sebelum absen, bisa setelah absen', function () {
    $order = Order::factory()->create([
        'teknisi_id' => $this->teknisi->id,
        'status' => OrderStatus::Terjadwal,
        'tanggal_jadwal' => now()->toDateString(),
    ]);
    $url = route('teknisi.order', ['order' => $order->id]);

    Livewire::actingAs($this->teknisi)->test(JadwalHariIni::class)
        ->assertSee('Anda belum absen hari ini')
        ->assertDontSee($url);

    DailyAttendance::create([
        'user_id' => $this->teknisi->id,
        'tanggal' => now()->toDateString(),
        'jam_datang' => now(),
        'status_datang' => DailyAttendanceStatus::Normal,
    ]);

    Livewire::actingAs($this->teknisi)->test(JadwalHariIni::class)
        ->assertDontSee('Anda belum absen hari ini')
        ->assertSee($url);
});

it('catatan admin tampil di detail order teknisi', function () {
    $order = Order::factory()->create([
        'teknisi_id' => $this->teknisi->id,
        'status' => OrderStatus::Dikerjakan,
        'catatan_admin' => 'Cek unit lantai 2, filter kotor.',
    ]);

    Livewire::actingAs($this->teknisi)->test(OrderDetail::class, ['order' => $order])
        ->assertSee('Catatan Admin')
        ->assertSee('Cek unit lantai 2, filter kotor.');
});

it('teknisi menandai "customer sudah bayar" (lapor bayar ke admin)', function () {
    $order = Order::factory()->create([
        'teknisi_id' => $this->teknisi->id,
        'status' => OrderStatus::Selesai,
    ]);

    Livewire::actingAs($this->teknisi)->test(OrderDetail::class, ['order' => $order])
        ->set('customerSudahBayar', true)
        ->assertHasNoErrors();

    $fresh = $order->fresh();
    expect($fresh->bayar_dilaporkan_teknisi)->toBeTrue();
    expect($fresh->bayar_dilaporkan_oleh)->toBe($this->teknisi->id);
    expect($fresh->bayar_dilaporkan_pada)->not->toBeNull();

    Livewire::actingAs($this->teknisi)->test(OrderDetail::class, ['order' => $fresh])
        ->set('customerSudahBayar', false);

    expect($order->fresh()->bayar_dilaporkan_teknisi)->toBeFalse();
    expect($order->fresh()->bayar_dilaporkan_pada)->toBeNull();
});

it('label check-in pertama memakai keterangan baru', function () {
    $order = Order::factory()->create([
        'teknisi_id' => $this->teknisi->id,
        'status' => OrderStatus::MenujuLokasi,
    ]);

    Livewire::actingAs($this->teknisi)->test(OrderDetail::class, ['order' => $order])
        ->assertSee('Titik Pertama Hari ini (Games 2)')
        ->assertSee('Check In Pertama');
});
