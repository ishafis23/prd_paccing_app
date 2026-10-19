<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Order;
use App\Models\TemporaryPhotoUpload;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Auto-save foto laporan teknisi (dev-plan/28): foto yang dipilih disimpan
 * sebagai draft di server & bisa dipulihkan setelah refresh.
 */
beforeEach(function () {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->teknisi = User::factory()->create();
    $this->teknisi->assignRole(RoleName::Teknisi->value);

    $this->order = Order::factory()->create([
        'teknisi_id' => $this->teknisi->id,
        'status' => OrderStatus::Dikerjakan,
    ]);

    $this->simpanDraft = fn (string $slot, string $nama = 'a.jpg') => $this->actingAs($this->teknisi)
        ->postJson(route('teknisi.temp-photo.store', $this->order), [
            'file' => UploadedFile::fake()->image($nama),
            'order_id' => $this->order->id,
            'field_name' => $slot,
        ]);
});

it('draft foto tersimpan dan bisa dipulihkan lewat index dengan URL preview', function () {
    ($this->simpanDraft)('fotoUnit.7.sebelum')->assertCreated()->assertJsonPath('data.url', fn ($u) => str_contains($u, 'temporary-photos/'));

    $res = $this->actingAs($this->teknisi)->getJson(route('teknisi.temp-photo.index', $this->order))->assertOk();

    expect($res->json('data'))->toHaveKey('fotoUnit.7.sebelum');
    expect($res->json('data')['fotoUnit.7.sebelum']['url'])->toContain('temporary-photos/');
});

it('unggah ulang slot yang sama menggantikan draft lama (satu slot satu foto)', function () {
    $pertama = ($this->simpanDraft)('fotoSebelum', 'satu.jpg')->json('data');
    ($this->simpanDraft)('fotoSebelum', 'dua.jpg')->assertCreated();

    expect(TemporaryPhotoUpload::withTrashed()->count())->toBe(1);
    Storage::disk('public')->assertMissing($pertama['file_path']);
});

it('draft yang filenya sudah hilang tidak ikut dipulihkan', function () {
    $data = ($this->simpanDraft)('fotoSesudah')->json('data');
    Storage::disk('public')->delete($data['file_path']);

    $res = $this->actingAs($this->teknisi)->getJson(route('teknisi.temp-photo.index', $this->order))->assertOk();

    expect($res->json('data'))->toBe([]);
});

it('teknisi lain tidak bisa menghapus draft milik orang lain', function () {
    $data = ($this->simpanDraft)('fotoSebelum')->json('data');

    $lain = User::factory()->create();
    $lain->assignRole(RoleName::Teknisi->value);

    $this->actingAs($lain)->deleteJson(route('teknisi.temp-photo.destroy', $data['id']))->assertForbidden();
    expect(TemporaryPhotoUpload::count())->toBe(1);

    $this->actingAs($this->teknisi)->deleteJson(route('teknisi.temp-photo.destroy', $data['id']))->assertOk();
    expect(TemporaryPhotoUpload::count())->toBe(0);
});
