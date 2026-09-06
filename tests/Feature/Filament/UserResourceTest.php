<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function usrRoleUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('owner dan admin boleh melihat dan mengelola pengguna', function () {
    $owner = usrRoleUser(RoleName::Owner->value);
    $admin = usrRoleUser(RoleName::Admin->value);

    expect(Gate::forUser($owner)->allows('viewAny', User::class))->toBeTrue();
    expect(Gate::forUser($owner)->allows('create', User::class))->toBeTrue();
    expect(Gate::forUser($admin)->allows('viewAny', User::class))->toBeTrue();
    expect(Gate::forUser($admin)->allows('create', User::class))->toBeTrue();
});

it('finance, hr, dan teknisi tidak boleh mengelola pengguna', function () {
    $finance = usrRoleUser(RoleName::Finance->value);
    $hr = usrRoleUser(RoleName::Hr->value);
    $teknisi = usrRoleUser(RoleName::Teknisi->value);

    expect(Gate::forUser($finance)->allows('viewAny', User::class))->toBeFalse();
    expect(Gate::forUser($finance)->allows('create', User::class))->toBeFalse();
    expect(Gate::forUser($hr)->allows('viewAny', User::class))->toBeFalse();
    expect(Gate::forUser($teknisi)->allows('viewAny', User::class))->toBeFalse();
});

it('admin tidak punya kemampuan hapus akun (B20: gunakan nonaktif)', function () {
    $admin = usrRoleUser(RoleName::Admin->value);
    $target = usrRoleUser(RoleName::Teknisi->value);

    expect(Gate::forUser($admin)->allows('delete', $target))->toBeFalse();
});

// Catatan: tiap skenario HTTP satu request per test — dua request
// ber-autentikasi berbeda-user dalam satu test kena AuthenticateSession
// (session hash mismatch -> redirect login).

it('halaman daftar pengguna terbuka untuk admin (HTTP)', function () {
    $this->actingAs(usrRoleUser(RoleName::Admin->value))
        ->get('/admin/users')
        ->assertSuccessful()
        ->assertSee('Pengguna');
});

it('halaman create pengguna terbuka untuk admin (HTTP)', function () {
    $this->actingAs(usrRoleUser(RoleName::Admin->value))
        ->get('/admin/users/create')
        ->assertSuccessful();
});

it('halaman edit pengguna terbuka untuk admin (HTTP)', function () {
    $teknisi = usrRoleUser(RoleName::Teknisi->value);

    $this->actingAs(usrRoleUser(RoleName::Admin->value))
        ->get("/admin/users/{$teknisi->id}/edit")
        ->assertSuccessful();
});

it('finance tidak bisa membuka daftar pengguna (HTTP 403)', function () {
    $this->actingAs(usrRoleUser(RoleName::Finance->value))
        ->get('/admin/users')
        ->assertForbidden();
});
