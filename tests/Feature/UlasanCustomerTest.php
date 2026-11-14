<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Filament\Resources\OrderReviewResource;
use App\Models\Order;
use App\Models\OrderReview;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->teknisi = tap(User::factory()->create(['name' => 'Teknisi Budi']), fn ($u) => $u->assignRole(RoleName::Teknisi->value));
    $this->order = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Selesai]);
    $this->order->pastikanResiToken();
    $this->urlResi = fn (): string => route('resi.show', ['order' => $this->order->id, 'token' => $this->order->fresh()->resi_token]);
    $this->urlUlasan = fn (): string => route('resi.ulasan', ['order' => $this->order->id, 'token' => $this->order->fresh()->resi_token]);
});

it('halaman resi order selesai menampilkan form ulasan', function () {
    $this->get(($this->urlResi)())->assertOk()->assertSee('Penilaian Pekerjaan')->assertSee('Kirim Penilaian');
});

it('customer mengirim ulasan lewat tautan resi; tersimpan dengan teknisi PIC; halaman lalu menampilkan hasilnya', function () {
    $this->post(($this->urlUlasan)(), ['rating' => 5, 'komentar' => 'Rapi dan ramah'])
        ->assertRedirect()->assertSessionHas('ulasan_status');

    $r = OrderReview::first();
    expect($r->rating)->toBe(5)
        ->and($r->komentar)->toBe('Rapi dan ramah')
        ->and($r->teknisi_id)->toBe($this->teknisi->id);

    $this->get(($this->urlResi)())->assertOk()->assertSee('Rapi dan ramah')->assertDontSee('Kirim Penilaian');
});

it('satu ulasan per order: kirim kedua ditolak; rating tidak valid ditolak', function () {
    $this->post(($this->urlUlasan)(), ['rating' => 4]);
    $this->post(($this->urlUlasan)(), ['rating' => 1])->assertSessionHas('ulasan_error');
    expect(OrderReview::count())->toBe(1)->and(OrderReview::first()->rating)->toBe(4);

    $baru = Order::factory()->create(['status' => OrderStatus::Selesai]);
    $baru->pastikanResiToken();
    $url = route('resi.ulasan', ['order' => $baru->id, 'token' => $baru->fresh()->resi_token]);
    $this->post($url, ['rating' => 9])->assertSessionHasErrors('rating');
    $this->post($url, [])->assertSessionHasErrors('rating');
    expect(OrderReview::count())->toBe(1);
});

it('token salah 404; order belum selesai tidak bisa diulas dan formnya tidak muncul', function () {
    $this->post(route('resi.ulasan', ['order' => $this->order->id, 'token' => 'salah']), ['rating' => 5])->assertNotFound();

    $this->order->update(['status' => OrderStatus::Dikerjakan]);
    $this->post(($this->urlUlasan)(), ['rating' => 5])->assertSessionHas('ulasan_error');
    expect(OrderReview::count())->toBe(0);
    $this->get(($this->urlResi)())->assertOk()->assertDontSee('Kirim Penilaian');
});

it('API /ulasan: rata-rata + daftar hanya milik teknisi login', function () {
    OrderReview::create(['order_id' => $this->order->id, 'teknisi_id' => $this->teknisi->id, 'rating' => 5, 'komentar' => 'Mantap']);
    $o2 = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Selesai]);
    OrderReview::create(['order_id' => $o2->id, 'teknisi_id' => $this->teknisi->id, 'rating' => 3]);
    $lain = Order::factory()->create(['status' => OrderStatus::Selesai]);
    OrderReview::create(['order_id' => $lain->id, 'teknisi_id' => $lain->teknisi_id, 'rating' => 1]);

    Sanctum::actingAs($this->teknisi);
    $res = $this->getJson('/api/v1/ulasan')->assertOk();

    expect($res->json('data.ringkasan.rata_rata'))->toBe(4)
        ->and($res->json('data.ringkasan.jumlah'))->toBe(2)
        ->and($res->json('data.daftar'))->toHaveCount(2)
        ->and($res->json('data.daftar.1.komentar'))->toBe('Mantap');
});

it('admin/owner bisa melihat daftar ulasan; teknisi tidak', function () {
    OrderReview::create(['order_id' => $this->order->id, 'teknisi_id' => $this->teknisi->id, 'rating' => 2, 'komentar' => 'Kurang bersih']);

    $admin = tap(User::factory()->create(), fn ($u) => $u->assignRole(RoleName::Admin->value));
    $this->actingAs($admin);
    Livewire::test(OrderReviewResource\Pages\ListOrderReviews::class)
        ->assertSuccessful()
        ->assertSee('Kurang bersih');

    $this->actingAs($this->teknisi);
    expect(OrderReviewResource::canViewAny())->toBeFalse();
});
