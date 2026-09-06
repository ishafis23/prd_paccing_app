<?php

use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

function userBerRoleAuth(RoleName $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('owner, admin, finance, hr bisa akses panel filament', function (RoleName $role) {
    $user = userBerRoleAuth($role);

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeTrue();
})->with([RoleName::Owner, RoleName::Admin, RoleName::Finance, RoleName::Hr]);

it('teknisi tidak bisa akses panel filament', function () {
    $teknisi = userBerRoleAuth(RoleName::Teknisi);

    expect($teknisi->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});

it('hanya admin yang boleh create/update/delete customer, finance/hr hanya lihat', function () {
    $admin = userBerRoleAuth(RoleName::Admin);
    $finance = userBerRoleAuth(RoleName::Finance);
    $customer = Customer::factory()->create();

    expect(Gate::forUser($admin)->allows('create', Customer::class))->toBeTrue();
    expect(Gate::forUser($admin)->allows('update', $customer))->toBeTrue();
    expect(Gate::forUser($finance)->allows('viewAny', Customer::class))->toBeTrue();
    expect(Gate::forUser($finance)->allows('create', Customer::class))->toBeFalse();
});

it('owner lolos semua gate walau tanpa policy spesifik (Gate::before)', function () {
    $owner = userBerRoleAuth(RoleName::Owner);

    expect(Gate::forUser($owner)->allows('modul-belum-didefinisikan'))->toBeTrue();
});

it('hr tidak boleh akses expense (finance-only)', function () {
    $hr = userBerRoleAuth(RoleName::Hr);
    $expense = Expense::factory()->create();

    expect(Gate::forUser($hr)->allows('viewAny', Expense::class))->toBeFalse();
    expect(Gate::forUser($hr)->allows('view', $expense))->toBeFalse();
});
