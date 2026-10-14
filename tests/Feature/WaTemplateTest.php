<?php

use App\Enums\RoleName;
use App\Filament\Resources\WaTemplateResource;
use App\Models\User;
use App\Models\WaTemplate;
use App\Services\WaTemplateService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('render mengganti placeholder dan membuang token yang tidak terisi', function () {
    $teks = app(WaTemplateService::class)->render(
        'Halo {nama} dari {nama_usaha} {tidak_ada}',
        ['nama' => 'Budi', 'nama_usaha' => 'Paccing'],
    );

    expect($teks)->toBe('Halo Budi dari Paccing');
});

it('teks memakai default bawaan bila baris template belum ada', function () {
    $teks = app(WaTemplateService::class)->teks('berangkat', [
        'nama' => 'Budi',
        'teknisi' => 'Andi',
        'layanan' => 'Cuci AC',
        'nama_usaha' => 'Paccing',
    ]);

    expect($teks)->toContain('Budi')
        ->toContain('Andi')
        ->toContain('Cuci AC');
});

it('teks memakai isi baris template yang aktif', function () {
    WaTemplate::create(['kode' => 'berangkat', 'nama' => 'x', 'isi' => 'Halo {nama}', 'aktif' => true]);

    expect(app(WaTemplateService::class)->teks('berangkat', ['nama' => 'Sari']))->toBe('Halo Sari');
});

it('teks mengembalikan null bila template dinonaktifkan admin', function () {
    WaTemplate::create(['kode' => 'berangkat', 'nama' => 'x', 'isi' => 'Halo {nama}', 'aktif' => false]);

    expect(app(WaTemplateService::class)->teks('berangkat', ['nama' => 'Sari']))->toBeNull();
});

it('hanya Owner/Admin bisa membuka Template Pesan WA', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);

    $this->actingAs($admin);
    expect(WaTemplateResource::canViewAny())->toBeTrue();

    $this->actingAs($teknisi);
    expect(WaTemplateResource::canViewAny())->toBeFalse();
});
