<?php

use App\Enums\RoleName;
use App\Filament\Resources\PayrollRunResource;
use App\Filament\Resources\TechnicianAdvanceResource;
use App\Filament\Resources\TechnicianPayrollProfileResource;
use App\Models\TechnicianAdvance;
use App\Models\TechnicianPayrollProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Smoke test panel Filament dev-plan/29 fase 1: kolom "Periode Gaji"/"Status
 * Potong" di daftar Kasbon, form cut-off di profil gaji, dan halaman Penggajian
 * harus tetap bisa dirender dengan data nyata (bukan hanya tabel kosong).
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);

    $this->teknisi = User::factory()->create(['name' => 'Bayu']);
    $this->teknisi->assignRole(RoleName::Teknisi->value);

    $this->profil = TechnicianPayrollProfile::create([
        'user_id' => $this->teknisi->id,
        'tanggal_masuk' => '2024-10-15',
        'cutoff_hari' => 23,
        'gaji_pokok' => 3000000,
        'potongan_bpjs_tk' => 0,
        'potongan_off_per_hari' => 100000,
        'denda_telat_per_hari' => 7500,
        'aktif' => true,
    ]);

    $this->kasbon = TechnicianAdvance::create([
        'user_id' => $this->teknisi->id,
        'tanggal' => '2026-09-19',
        'nominal' => 500000,
        'keterangan' => 'Kasbon Bayu 19 Sep',
        'dicatat_oleh' => $this->admin->id,
    ]);
});

it('daftar Kasbon bisa dirender bersama kolom periode & status potong', function () {
    $this->actingAs($this->admin)
        ->get(TechnicianAdvanceResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Bayu')
        ->assertSee('Periode Gaji')
        ->assertSee('Status Potong');
});

it('form tambah Kasbon bisa dirender', function () {
    $this->actingAs($this->admin)
        ->get(TechnicianAdvanceResource::getUrl('create'))
        ->assertOk();
});

it('halaman profil gaji menampilkan isian cut-off', function () {
    $this->actingAs($this->admin)
        ->get(TechnicianPayrollProfileResource::getUrl('edit', ['record' => $this->profil]))
        ->assertOk()
        ->assertSee('Tanggal Cut-off Periode Gaji');
});

it('halaman Penggajian bisa dirender', function () {
    $this->actingAs($this->admin)
        ->get(PayrollRunResource::getUrl('index'))
        ->assertOk();
});
