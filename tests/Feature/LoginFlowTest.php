<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Livewire\Auth\Login;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

it('admin login lewat halaman login diarahkan ke /admin', function () {
    $admin = User::factory()->create(['password' => 'password']);
    $admin->assignRole(RoleName::Admin->value);

    Livewire::test(Login::class)
        ->set('email', $admin->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect('/admin');
});

it('teknisi login lewat halaman login diarahkan ke /teknisi', function () {
    $teknisi = User::factory()->create(['password' => 'password']);
    $teknisi->assignRole(RoleName::Teknisi->value);

    Livewire::test(Login::class)
        ->set('email', $teknisi->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect('/teknisi');
});

it('password salah menampilkan error, tidak login', function () {
    $user = User::factory()->create(['password' => 'password']);
    $user->assignRole(RoleName::Teknisi->value);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'salah')
        ->call('login')
        ->assertHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('user tanpa role ditolak walau password benar', function () {
    $user = User::factory()->create(['password' => 'password']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('halaman login bisa diakses via HTTP tanpa error', function () {
    $this->get('/login')->assertSuccessful();
});

it('halaman mobile teknisi render sukses via HTTP end-to-end', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Terjadwal]);

    $this->actingAs($teknisi)->get('/teknisi')->assertSuccessful();
    $this->actingAs($teknisi)->get('/teknisi/order/'.$order->id)->assertSuccessful();
    $this->actingAs($teknisi)->get('/teknisi/riwayat')->assertSuccessful();
    $this->actingAs($teknisi)->get('/teknisi/capaian')->assertSuccessful();
    $this->actingAs($teknisi)->get('/teknisi/akun')->assertSuccessful();
});

it('bottom nav tampil di semua halaman teknisi dan menyorot tab aktif', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);

    $this->actingAs($teknisi)->get('/teknisi')
        ->assertSuccessful()
        ->assertSee('Jadwal')
        ->assertSee('Riwayat')
        ->assertSee('Capaian')
        ->assertSee('Akun');
});

it('halaman akun menampilkan data user dan tombol keluar', function () {
    $teknisi = User::factory()->create(['name' => 'Teknisi Uji', 'phone' => '081200001111']);
    $teknisi->assignRole(RoleName::Teknisi->value);

    $this->actingAs($teknisi)->get('/teknisi/akun')
        ->assertSuccessful()
        ->assertSee('Teknisi Uji')
        ->assertSee($teknisi->email)
        ->assertSee('081200001111')
        ->assertSee('Keluar');
});

it('tombol keluar di halaman akun benar-benar logout', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);

    $this->actingAs($teknisi)->post('/logout')->assertRedirect('/login');
    expect(auth()->check())->toBeFalse();
});
