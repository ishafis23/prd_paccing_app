<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Livewire\Teknisi\LaporanPengeluaran;
use App\Livewire\Teknisi\OrderDetail;
use App\Models\Order;
use App\Models\TeknisiExpense;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    config(['expense.foto_wajib' => true, 'kendala.bukti_wajib' => true]);
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->teknisi = tap(User::factory()->create(), fn ($u) => $u->assignRole(RoleName::Teknisi->value));

    // Livewire::test() mensyaratkan UploadedFile::fake(); ukuran dibedakan agar isi file berbeda.
    $this->foto = fn (int $seed): UploadedFile => UploadedFile::fake()->image("bukti{$seed}.png", 40 + $seed * 7, 40 + $seed * 5);

    $this->isiForm = fn ($c) => $c->set('tanggal_input', today()->format('Y-m-d'))->set('kategori', 'bensin')
        ->set('qty', 1)->set('harga', 20000)->set('nominal', 20000);
});

it('web pengeluaran: tanpa foto ditolak; dengan foto tersimpan; foto sama dipakai lagi ditolak', function () {
    $c = Livewire::actingAs($this->teknisi)->test(LaporanPengeluaran::class);

    ($this->isiForm)($c)->call('submitPengeluaran')->assertHasErrors(['foto' => 'required']);
    expect(TeknisiExpense::count())->toBe(0);

    ($this->isiForm)($c)->set('foto', ($this->foto)(1))->call('submitPengeluaran')->assertHasNoErrors();
    $e = TeknisiExpense::first();
    expect($e)->not->toBeNull()->and($e->foto_hash)->toHaveLength(64);
    Storage::disk('public')->assertExists($e->foto_path);

    $c2 = Livewire::actingAs($this->teknisi)->test(LaporanPengeluaran::class);
    ($this->isiForm)($c2)->set('foto', ($this->foto)(1))->call('submitPengeluaran')->assertHasErrors('foto');
    expect(TeknisiExpense::count())->toBe(1);
});

it('web kendala: wajib bukti + pihak; tersimpan sebagai riwayat bukti kendala', function () {
    $order = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Terjadwal, 'tanggal_jadwal' => today()]);
    $c = Livewire::actingAs($this->teknisi)->test(OrderDetail::class, ['order' => $order]);

    $c->set('jenisKendala', 'besok')->set('alasanKendala', 'Customer tidak di rumah')
        ->call('tandaiKendala')->assertHasErrors(['buktiKendala', 'pihakKendala']);
    expect($order->fresh()->status)->toBe(OrderStatus::Terjadwal);

    $c->set('buktiKendala', ($this->foto)(5))->set('pihakKendala', 'customer')->call('tandaiKendala')->assertHasNoErrors();

    $bukti = $order->kendalaBuktis()->first();
    expect($order->fresh()->status)->toBe(OrderStatus::Terkendala)
        ->and($bukti->pihak)->toBe('customer')
        ->and($bukti->foto_path)->not->toBeNull();
    Storage::disk('public')->assertExists($bukti->foto_path);
});
