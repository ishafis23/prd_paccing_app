<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('seeder membuat 5 role sesuai PRD', function () {
    expect(Role::count())->toBe(5);

    foreach (RoleName::cases() as $role) {
        expect(Role::where('name', $role->value)->exists())->toBeTrue();
    }
});

it('user bisa memegang multi-role (owner rangkap backoffice)', function () {
    $owner = User::factory()->create();
    $owner->syncRoles([RoleName::Owner->value, RoleName::Admin->value, RoleName::Finance->value]);

    expect($owner->hasRole(RoleName::Owner->value))->toBeTrue()
        ->and($owner->hasRole(RoleName::Admin->value))->toBeTrue()
        ->and($owner->hasAllRoles([RoleName::Owner->value, RoleName::Finance->value]))->toBeTrue();
});

it('owner adalah super admin lewat Gate::before', function () {
    $owner = User::factory()->create();
    $owner->assignRole(RoleName::Owner->value);

    Gate::before(fn ($user) => $user->hasRole(RoleName::Owner->value) ? true : null);

    expect(Gate::forUser($owner)->allows('modul-belum-didefinisikan'))->toBeTrue();
});

it('teknisi bukan owner dan tidak lolos gate owner', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);

    expect($teknisi->isOwner())->toBeFalse()
        ->and($teknisi->hasRole(RoleName::Owner->value))->toBeFalse();
});

it('user tanpa role tidak punya akses apa pun', function () {
    $anonim = User::factory()->create();

    expect($anonim->roles)->toHaveCount(0)
        ->and($anonim->hasAnyRole(RoleName::cases()))->toBeFalse();
});

it('database seeder membuat akun demo owner, admin, dan teknisi', function () {
    $this->seed(\Database\Seeders\DatabaseSeeder::class);

    expect(User::where('email', 'owner@paccing.test')->first())
        ->not->toBeNull()
        ->isOwner()->toBeTrue();

    expect(User::where('email', 'admin@paccing.test')->first())
        ->not->toBeNull()
        ->hasRole(RoleName::Admin->value)->toBeTrue();

    $teknisi = User::where('email', 'teknisi1@paccing.test')->first();
    expect($teknisi)->not->toBeNull()
        ->and($teknisi->hasRole(RoleName::Teknisi->value))->toBeTrue();

    expect(\App\Models\ServiceCatalog::count())->toBeGreaterThanOrEqual(5);
});
