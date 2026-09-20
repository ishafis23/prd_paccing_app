<?php

use App\Enums\RoleName;
use App\Filament\Resources\TitikResource\Pages\CreateTitik;
use App\Filament\Resources\TitikResource\Pages\EditTitik;
use App\Filament\Resources\TitikResource\Pages\ListTitiks;
use App\Models\Titik;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);
});

it('admin bisa membuat Titik baru', function () {
    Livewire::actingAs($this->admin)
        ->test(CreateTitik::class)
        ->fillForm([
            'nama' => 'Titik 1',
            'jam' => '08:15',
            'urutan' => 1,
            'aktif' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Titik::where('nama', 'Titik 1')->exists())->toBeTrue();
});

it('admin bisa mengedit Titik & menonaktifkannya', function () {
    $titik = Titik::factory()->create(['nama' => 'Titik Lama', 'aktif' => true]);

    Livewire::actingAs($this->admin)
        ->test(EditTitik::class, ['record' => $titik->getRouteKey()])
        ->fillForm(['nama' => 'Titik Baru', 'aktif' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($titik->fresh()->nama)->toBe('Titik Baru')
        ->and($titik->fresh()->aktif)->toBeFalse();
});

it('daftar Titik terurut berdasarkan urutan', function () {
    Titik::factory()->create(['nama' => 'Titik B', 'urutan' => 2]);
    Titik::factory()->create(['nama' => 'Titik A', 'urutan' => 1]);

    Livewire::actingAs($this->admin)
        ->test(ListTitiks::class)
        ->assertSuccessful()
        ->assertSeeInOrder(['Titik A', 'Titik B']);
});
