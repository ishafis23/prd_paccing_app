<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Livewire\Teknisi\Akun;
use App\Livewire\Teknisi\JadwalHariIni;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Fase C revisi portal teknisi: foto profil (Poin 3) & aksi kirim resi WA (Poin 5).
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mk = function (RoleName $role): User {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    };
});

it('teknisi mengunggah foto profil', function () {
    Storage::fake('public');
    $teknisi = ($this->mk)(RoleName::Teknisi);

    Livewire::actingAs($teknisi)->test(Akun::class)
        ->set('fotoProfil', UploadedFile::fake()->image('me.jpg'))
        ->call('simpanFotoProfil')
        ->assertHasNoErrors();

    expect($teknisi->fresh()->foto_profil)->not->toBeNull();
});

it('jadwal menampilkan foto profil teknisi bila ada', function () {
    Storage::fake('public');
    $teknisi = ($this->mk)(RoleName::Teknisi);
    $teknisi->foto_profil = 'foto-profil/me.jpg';
    $teknisi->save();
    Storage::disk('public')->put('foto-profil/me.jpg', 'x');

    Livewire::actingAs($teknisi)->test(JadwalHariIni::class)
        ->assertSee(asset('storage/foto-profil/me.jpg'));
});

it('aksi Kirim Resi (WA) tersembunyi bila belum ada device WhatsApp', function () {
    $admin = ($this->mk)(RoleName::Admin);
    $order = Order::factory()->create([
        'resi_token' => 'resitokenabc123',
        'status' => OrderStatus::Selesai,
    ]);

    Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->assertTableActionHidden('kirimResi', $order);
});
