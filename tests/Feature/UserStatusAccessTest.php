<?php

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Livewire\Auth\Login;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('login ditolak untuk user nonaktif (B20)', function () {
    $nonaktif = User::factory()->create(['password' => 'password']);
    $nonaktif->assignRole(RoleName::Admin->value);
    $nonaktif->update(['status' => UserStatus::Nonaktif]);

    Livewire::test(Login::class)
        ->set('email', $nonaktif->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('user aktif tetap bisa login', function () {
    $admin = User::factory()->create(['password' => 'password']);
    $admin->assignRole(RoleName::Admin->value);

    Livewire::test(Login::class)
        ->set('email', $admin->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect('/admin');
});

it('teknisi yang dinonaktifkan ditendang dari halaman mobile /teknisi', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    $teknisi->update(['status' => UserStatus::Nonaktif]);

    $this->actingAs($teknisi)
        ->get('/teknisi')
        ->assertRedirect('/login');
});

it('admin yang dinonaktifkan ditolak masuk panel /admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);
    $admin->update(['status' => UserStatus::Nonaktif]);

    // canAccessPanel=false -> Filament Authenticate menolak 403 (akses panel);
    // login juga sudah diblokir di halaman login.
    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertForbidden();
});

it('teknisi aktif masih bisa membuka halaman /teknisi (regresi)', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);

    $this->actingAs($teknisi)->get('/teknisi')->assertSuccessful();
});
