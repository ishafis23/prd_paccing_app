<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Order;
use App\Models\OrderVideo;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->teknisi = tap(User::factory()->create(), fn ($u) => $u->assignRole(RoleName::Teknisi->value));
    $this->order = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Dikerjakan, 'tanggal_jadwal' => today()]);
    $this->video = fn (string $nama = 'kerja.mp4', string $mime = 'video/mp4', int $kb = 500): UploadedFile => UploadedFile::fake()->create($nama, $kb, $mime);
    Sanctum::actingAs($this->teknisi);
});

it('API: tambah video mp4, tampil di detail order, lalu hapus', function () {
    $res = $this->post("/api/v1/order/{$this->order->id}/video", ['video' => ($this->video)(), 'keterangan' => 'Proses cuci'], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.video.0.keterangan', 'Proses cuci')
        ->assertJsonPath('data.video.0.bisa_hapus', true);

    $v = OrderVideo::first();
    Storage::disk('public')->assertExists($v->path);
    expect($res->json('data.video.0.url'))->toContain('order-videos');

    $this->deleteJson("/api/v1/order/{$this->order->id}/video/{$v->id}")->assertOk()->assertJsonPath('data.video', []);
    Storage::disk('public')->assertMissing($v->path);
    expect(OrderVideo::count())->toBe(0);
});

it('format non-video ditolak 422', function () {
    $this->post("/api/v1/order/{$this->order->id}/video", ['video' => ($this->video)('x.exe', 'application/octet-stream')], ['Accept' => 'application/json'])
        ->assertStatus(422);
    $this->post("/api/v1/order/{$this->order->id}/video", ['video' => ($this->video)('x.mp4', 'image/png')], ['Accept' => 'application/json'])
        ->assertStatus(422);

    expect(OrderVideo::count())->toBe(0);
});

it('melebihi batas ukuran atau jumlah ditolak', function () {
    config(['video.maks_mb' => 1]);
    $this->post("/api/v1/order/{$this->order->id}/video", ['video' => ($this->video)('besar.mp4', 'video/mp4', 2048)], ['Accept' => 'application/json'])
        ->assertStatus(422);

    config(['video.maks_mb' => 50, 'video.maks_per_order' => 1]);
    $this->post("/api/v1/order/{$this->order->id}/video", ['video' => ($this->video)()], ['Accept' => 'application/json'])->assertOk();
    $this->post("/api/v1/order/{$this->order->id}/video", ['video' => ($this->video)('lagi.mp4')], ['Accept' => 'application/json'])->assertStatus(422);
});

it('order tim lain 404 dan video rekan tidak bisa dihapus', function () {
    $lain = Order::factory()->create(['status' => OrderStatus::Dikerjakan]);
    $this->post("/api/v1/order/{$lain->id}/video", ['video' => ($this->video)()], ['Accept' => 'application/json'])->assertStatus(404);

    $rekan = tap(User::factory()->create(), fn ($u) => $u->assignRole(RoleName::Teknisi->value));
    $this->order->orderTechnicians()->create(['teknisi_id' => $rekan->id]);
    $v = OrderVideo::create(['order_id' => $this->order->id, 'user_id' => $rekan->id, 'path' => 'order-videos/x.mp4', 'ukuran' => 1]);

    $this->deleteJson("/api/v1/order/{$this->order->id}/video/{$v->id}")->assertStatus(422);
    expect(OrderVideo::count())->toBe(1);
});

it('portal web: upload & hapus video lewat form biasa', function () {
    $this->actingAs($this->teknisi)
        ->post(route('teknisi.video.store', $this->order->id), ['video' => ($this->video)()])
        ->assertRedirect()->assertSessionHas('video_status');
    expect(OrderVideo::count())->toBe(1);

    $this->actingAs($this->teknisi)
        ->post(route('teknisi.video.store', $this->order->id), ['video' => ($this->video)('a.txt', 'text/plain')])
        ->assertRedirect()->assertSessionHas('video_error');

    $v = OrderVideo::first();
    $this->actingAs($this->teknisi)->delete(route('teknisi.video.destroy', [$this->order->id, $v->id]))->assertRedirect();
    expect(OrderVideo::count())->toBe(0);
});
