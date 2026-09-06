<?php

use App\Enums\RoleName;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);
});

it('guest diarahkan ke login saat akses /admin', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('teknisi login tapi tidak bisa akses /admin (403)', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);

    $this->actingAs($teknisi)->get('/admin')->assertForbidden();
});

it('semua halaman index resource backoffice bisa diakses admin tanpa error', function (string $url) {
    $this->actingAs($this->admin)->get($url)->assertSuccessful();
})->with([
    '/admin',
    '/admin/customers',
    '/admin/service-catalogs',
    '/admin/orders',
    '/admin/stock-items',
    '/admin/stock-movements',
    '/admin/payments',
    '/admin/service-reminders',
    '/admin/expenses',
    '/admin/incomes',
]);
