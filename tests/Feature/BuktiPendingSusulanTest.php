<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Teknisi\OrderDetail;
use App\Models\Order;
use App\Models\User;
use App\Services\TeknisiService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->teknisi = tap(User::factory()->create(), fn ($u) => $u->assignRole(RoleName::Teknisi->value));

    $this->pendingSore = fn (array $x = []): Order => Order::factory()->create($x + [
        'teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Terjadwal, 'tanggal_jadwal' => today(),
        'jenis_kendala' => 'sore', 'alasan_kendala' => 'No respon',
    ]);
});

it('web: unggah screenshot PNG di kartu pending tersimpan sebagai JPG dan tercatat', function () {
    $order = ($this->pendingSore)();

    Livewire::actingAs($this->teknisi)->test(OrderDetail::class, ['order' => $order])
        ->assertSee('Belum ada bukti customer sudah diinfokan')
        ->set('buktiPending', UploadedFile::fake()->image('ss-chat.png', 300, 600))
        ->assertHasNoErrors();

    $bukti = $order->kendalaBuktis()->first();
    expect($bukti)->not->toBeNull()
        ->and($bukti->foto_path)->toEndWith('.jpg')
        ->and($bukti->jenis)->toBe('sore')
        ->and($bukti->alasan)->toBe('No respon');
    Storage::disk('public')->assertExists($bukti->foto_path);
    expect(getimagesize(Storage::disk('public')->path($bukti->foto_path))[2])->toBe(IMAGETYPE_JPEG);
});

it('web: file bukan gambar ditolak', function () {
    $order = ($this->pendingSore)();

    Livewire::actingAs($this->teknisi)->test(OrderDetail::class, ['order' => $order])
        ->set('buktiPending', UploadedFile::fake()->create('catatan.pdf', 10, 'application/pdf'))
        ->assertHasErrors('buktiPending');

    expect($order->kendalaBuktis()->count())->toBe(0);
});

it('api: POST /order/{id}/kendala/bukti menyimpan JPG; order yang tidak pending ditolak', function () {
    $order = ($this->pendingSore)();
    $biasa = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Terjadwal, 'tanggal_jadwal' => today()]);
    Sanctum::actingAs($this->teknisi);

    $this->post("/api/v1/order/{$biasa->id}/kendala/bukti", ['bukti' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])
        ->assertStatus(422);
    expect($biasa->kendalaBuktis()->count())->toBe(0);

    $this->post("/api/v1/order/{$order->id}/kendala/bukti", ['bukti' => UploadedFile::fake()->image('ss.png', 200, 400)], ['Accept' => 'application/json'])
        ->assertOk();

    expect($order->kendalaBuktis()->first()->foto_path)->toEndWith('.jpg');
});

it('service: teknisi lain tidak boleh menambah bukti pada order orang', function () {
    $order = ($this->pendingSore)();
    $lain = tap(User::factory()->create(), fn ($u) => $u->assignRole(RoleName::Teknisi->value));

    expect(fn () => app(TeknisiService::class)->tambahBuktiKendala($order, $lain, 'kendala-bukti/x.jpg'))
        ->toThrow('Order ini bukan tugas teknisi Anda.');
});
