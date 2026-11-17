<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Filament\Pages\PetaTeknisi;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function ptUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('halaman peta teknisi hanya bisa diakses admin/finance/hr, bukan teknisi', function (string $role, bool $boleh) {
    $user = ptUser($role);

    $this->actingAs($user)->get(PetaTeknisi::getUrl())
        ->{$boleh ? 'assertOk' : 'assertForbidden'}();
})->with([
    ['admin', true],
    ['finance', true],
    ['hr', true],
    ['teknisi', false],
]);

it('teknisi tanpa order aktif tetap tampil sebagai di luar jam kerja dengan posisi terakhir', function () {
    $admin = ptUser(RoleName::Admin->value);
    $teknisiAktif = ptUser(RoleName::Teknisi->value);
    $teknisiIdle = ptUser(RoleName::Teknisi->value);

    $customer = Customer::factory()->create(['nama' => 'Budi Makmur']);
    Order::factory()->create([
        'customer_id' => $customer->id,
        'teknisi_id' => $teknisiAktif->id,
        'status' => OrderStatus::MenujuLokasi,
    ]);
    Order::factory()->create([
        'teknisi_id' => $teknisiIdle->id,
        'status' => OrderStatus::Selesai,
    ]);

    $response = $this->actingAs($admin)->get(PetaTeknisi::getUrl());

    $response->assertOk()
        ->assertSee($teknisiAktif->name)
        ->assertSee($teknisiIdle->name)
        ->assertSee('Di luar jam kerja');
});

it('teknisi tanpa sinyal gps tetap muncul di daftar dengan status belum ada sinyal', function () {
    $admin = ptUser(RoleName::Admin->value);
    $teknisi = ptUser(RoleName::Teknisi->value);

    Order::factory()->create([
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::Dikerjakan,
    ]);

    $this->actingAs($admin)->get(PetaTeknisi::getUrl())
        ->assertOk()
        ->assertSee($teknisi->name)
        ->assertSee('belum ada sinyal');
});

it('teknisi dengan sinyal gps terbaru tampil live di daftar & peta', function () {
    $admin = ptUser(RoleName::Admin->value);
    $teknisi = ptUser(RoleName::Teknisi->value);
    $teknisi->update([
        'last_latitude' => -5.147665,
        'last_longitude' => 119.432732,
        'last_location_at' => now(),
    ]);

    Order::factory()->create([
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::MenujuLokasi,
    ]);

    $this->actingAs($admin)->get(PetaTeknisi::getUrl())
        ->assertOk()
        ->assertSee($teknisi->name)
        ->assertSee('Live');
});

it('teknisi dalam jam kerja (absen datang, belum pulang) tanpa order aktif tampil di peta', function () {
    $admin = ptUser(RoleName::Admin->value);
    $bekerja = ptUser(RoleName::Teknisi->value);
    $sudahPulang = ptUser(RoleName::Teknisi->value);
    $belumAbsen = ptUser(RoleName::Teknisi->value);

    $bekerja->update(['last_latitude' => -6.2, 'last_longitude' => 106.8, 'last_location_at' => now()]);
    \App\Models\DailyAttendance::create(['user_id' => $bekerja->id, 'tanggal' => today(), 'jam_datang' => now()->subHours(2)]);
    \App\Models\DailyAttendance::create(['user_id' => $sudahPulang->id, 'tanggal' => today(), 'jam_datang' => now()->subHours(8), 'jam_pulang' => now()->subHour()]);

    $this->actingAs($admin)->get(PetaTeknisi::getUrl())
        ->assertOk()
        ->assertSee($bekerja->name)
        ->assertSee('Jam kerja')
        // Yang sudah pulang / belum absen tetap terdaftar (posisi terakhir) sebagai di luar jam kerja.
        ->assertSee($sudahPulang->name)
        ->assertSee($belumAbsen->name)
        ->assertSee('Di luar jam kerja');
});

it('penanda aktif: API mengisi last_seen_at (waktu server) dan peta menampilkan App aktif', function () {
    $admin = ptUser(RoleName::Admin->value);
    $bayu = ptUser(RoleName::Teknisi->value);
    $bayu->update(['name' => 'Bayu']);

    expect($bayu->fresh()->last_seen_at)->toBeNull();

    Laravel\Sanctum\Sanctum::actingAs($bayu);
    $this->getJson('/api/v1/absensi/hari-ini', ['Accept' => 'application/json']);

    expect($bayu->fresh()->last_seen_at)->not->toBeNull()
        ->and($bayu->fresh()->last_seen_at->diffInSeconds(now(), true))->toBeLessThan(5);

    // GPS belum masuk tapi app aktif → peringatan.
    $this->actingAs($admin, 'web')->get(PetaTeknisi::getUrl())
        ->assertOk()
        ->assertSee('App aktif')
        ->assertSee('App aktif tetapi GPS belum masuk');
});

it('last_seen_at lama ditampilkan sebagai App terakhir dibuka, bukan aktif', function () {
    $admin = ptUser(RoleName::Admin->value);
    $t = ptUser(RoleName::Teknisi->value);
    $t->update(['last_seen_at' => now()->subHours(3)]);

    $this->actingAs($admin)->get(PetaTeknisi::getUrl())
        ->assertOk()
        ->assertSee('App tidak aktif')
        ->assertDontSee('● App aktif', false);
});

it('logout langsung menjadikan status app "Sudah logout", login lagi menjadikan aktif', function () {
    $admin = ptUser(RoleName::Admin->value);
    $t = ptUser(RoleName::Teknisi->value);
    $t->update(['last_seen_at' => now()]);

    expect($t->fresh()->statusApp())->toBe('aktif');

    Laravel\Sanctum\Sanctum::actingAs($t);
    $this->postJson('/api/v1/logout', [], ['Accept' => 'application/json'])->assertOk();

    expect($t->fresh()->statusApp())->toBe('keluar');

    $this->actingAs($admin, 'web')->get(PetaTeknisi::getUrl())->assertOk()->assertSee('Sudah logout');

    $t->update(['last_seen_at' => now()->addSecond()]);
    expect($t->fresh()->statusApp())->toBe('aktif');
});
