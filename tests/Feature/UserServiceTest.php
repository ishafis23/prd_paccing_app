<?php

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Services\UserService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->usrService = new UserService;
});

function usrAkun(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function usrDataUser(array $overrides = []): array
{
    return array_merge([
        'name' => 'Teknisi Baru',
        'email' => 'teknisi.baru@example.test',
        'phone' => '081299887766',
        'password' => 'password123',
        'role' => RoleName::Teknisi->value,
        'status' => UserStatus::Aktif->value,
    ], $overrides);
}

it('admin dapat membuat akun teknisi baru dengan role dan status aktif', function () {
    $admin = usrAkun(RoleName::Admin->value);

    $user = $this->usrService->createUser(usrDataUser(), $admin);

    expect($user->hasRole(RoleName::Teknisi->value))->toBeTrue()
        ->and($user->status)->toBe(UserStatus::Aktif)
        ->and($user->phone)->toBe('081299887766')
        ->and(Hash::check('password123', $user->password))->toBeTrue();
});

it('email duplikat ditolak saat membuat akun', function () {
    $admin = usrAkun(RoleName::Admin->value);
    usrAkun(RoleName::Teknisi->value)->update(['email' => 'dipakai@example.test']);

    $this->usrService->createUser(usrDataUser(['email' => 'dipakai@example.test']), $admin);
})->throws(BusinessRuleException::class, 'Email sudah digunakan');

it('admin tidak bisa membuat akun ber-role owner', function () {
    $admin = usrAkun(RoleName::Admin->value);

    $this->usrService->createUser(usrDataUser(['role' => RoleName::Owner->value]), $admin);
})->throws(AuthorizationException::class);

it('owner dapat membuat akun ber-role owner', function () {
    $owner = usrAkun(RoleName::Owner->value);

    $user = $this->usrService->createUser(usrDataUser([
        'role' => RoleName::Owner->value,
        'email' => 'owner2@example.test',
    ]), $owner);

    expect($user->hasRole(RoleName::Owner->value))->toBeTrue();
});

it('admin tidak bisa mengubah akun owner', function () {
    $admin = usrAkun(RoleName::Admin->value);
    $owner = usrAkun(RoleName::Owner->value);

    $this->usrService->updateUser($owner, ['name' => 'Diubah Admin'], $admin);
})->throws(AuthorizationException::class);

it('admin tidak bisa mengubah akun sendiri', function () {
    $admin = usrAkun(RoleName::Admin->value);

    $this->usrService->updateUser($admin, ['name' => 'Nama Baru'], $admin);
})->throws(AuthorizationException::class);

it('role akun sendiri tidak bisa diubah walau oleh owner', function () {
    $owner = usrAkun(RoleName::Owner->value);

    $this->usrService->updateUser($owner, ['role' => RoleName::Teknisi->value], $owner);
})->throws(BusinessRuleException::class, 'Role akun sendiri');

it('admin dapat menonaktifkan teknisi lain', function () {
    $admin = usrAkun(RoleName::Admin->value);
    $teknisi = usrAkun(RoleName::Teknisi->value);

    $updated = $this->usrService->updateUser($teknisi, ['status' => UserStatus::Nonaktif->value], $admin);

    expect($updated->status)->toBe(UserStatus::Nonaktif);
});

it('admin dapat mengganti role teknisi menjadi admin', function () {
    $admin = usrAkun(RoleName::Admin->value);
    $teknisi = usrAkun(RoleName::Teknisi->value);

    $updated = $this->usrService->updateUser($teknisi, ['role' => RoleName::Finance->value], $admin);

    expect($updated->hasRole(RoleName::Finance->value))->toBeTrue()
        ->and($updated->hasRole(RoleName::Teknisi->value))->toBeFalse();
});

it('reset password bekerja dan password lama tidak valid lagi', function () {
    $admin = usrAkun(RoleName::Admin->value);
    $teknisi = usrAkun(RoleName::Teknisi->value);

    $updated = $this->usrService->resetPassword($teknisi, 'rahasiaBaru99', $admin);

    expect(Hash::check('rahasiaBaru99', $updated->password))->toBeTrue()
        ->and(Hash::check('password', $updated->password))->toBeFalse();
});

it('admin tidak bisa reset password akun owner', function () {
    $admin = usrAkun(RoleName::Admin->value);
    $owner = usrAkun(RoleName::Owner->value);

    $this->usrService->resetPassword($owner, 'rahasiaBaru99', $admin);
})->throws(AuthorizationException::class);

it('password baru kurang dari 8 karakter ditolak', function () {
    $admin = usrAkun(RoleName::Admin->value);
    $teknisi = usrAkun(RoleName::Teknisi->value);

    $this->usrService->resetPassword($teknisi, 'pendek', $admin);
})->throws(BusinessRuleException::class, 'Password minimal 8');
