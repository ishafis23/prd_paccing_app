<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkReport;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    Storage::disk('public')->put('work-reports/sebelum.jpg', 'foto-sebelum');
    Storage::disk('public')->put('work-reports/sesudah.jpg', 'foto-sesudah');

    $this->mkTeknisi = fn (): User => tap(User::factory()->create(), fn (User $u) => $u->assignRole(RoleName::Teknisi->value));
    $this->mkAdmin = fn (): User => tap(User::factory()->create(), fn (User $u) => $u->assignRole(RoleName::Admin->value));
});

function fotoOrderSelesai(User $teknisi): Order
{
    $order = Order::factory()->create([
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::Selesai,
    ]);

    WorkReport::factory()->create([
        'order_id' => $order->id,
        'teknisi_id' => $teknisi->id,
        'catatan_pengerjaan' => 'Selesai cuci AC.',
        'foto_sebelum' => 'work-reports/sebelum.jpg',
        'foto_sesudah' => 'work-reports/sesudah.jpg',
    ]);

    return $order;
}

it('teknisi melihat foto sebelum & sesudah di preview detail order', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = fotoOrderSelesai($teknisi);

    $this->actingAs($teknisi)
        ->get("/teknisi/order/{$order->id}")
        ->assertSuccessful()
        ->assertSee('storage/work-reports/sebelum.jpg')
        ->assertSee('storage/work-reports/sesudah.jpg')
        ->assertSee('Foto Pengerjaan');
});

it('halaman riwayat teknisi menampilkan thumbnail foto sesudah', function () {
    $teknisi = ($this->mkTeknisi)();
    fotoOrderSelesai($teknisi);

    $this->actingAs($teknisi)
        ->get('/teknisi/riwayat')
        ->assertSuccessful()
        ->assertSee('storage/work-reports/sesudah.jpg');
});

it('admin melihat foto sebelum & sesudah di detail order', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = fotoOrderSelesai($teknisi);

    $this->actingAs($admin)
        ->get("/admin/orders/{$order->id}")
        ->assertSuccessful()
        ->assertSee('storage/work-reports/sebelum.jpg')
        ->assertSee('storage/work-reports/sesudah.jpg');
});

it('resi publik menampilkan foto pengerjaan', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = fotoOrderSelesai($teknisi);

    $this->get("/resi/{$order->id}/{$order->pastikanResiToken()}")
        ->assertSuccessful()
        ->assertSee('storage/work-reports/sebelum.jpg')
        ->assertSee('storage/work-reports/sesudah.jpg');
});
