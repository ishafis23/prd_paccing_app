<?php

use App\Enums\RoleName;
use App\Filament\Resources\StockItemResource\Pages\ListStockItems;
use App\Filament\Resources\StockMovementResource\Pages\ListStockMovements;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);
    $this->teknisi = User::factory()->create();
    $this->teknisi->assignRole(RoleName::Teknisi->value);
});

it('admin stok masuk menambah stok_saat_ini lewat aksi tabel', function () {
    $item = StockItem::factory()->create(['stok_saat_ini' => 5]);

    Livewire::actingAs($this->admin)
        ->test(ListStockItems::class)
        ->callTableAction('stokMasuk', $item, data: ['jumlah' => 10, 'keterangan' => 'Restock']);

    expect($item->fresh()->stok_saat_ini)->toBe(15);
    expect($item->movements()->where('jenis', 'masuk')->count())->toBe(1);
});

it('penyesuaian negatif mengurangi stok lewat aksi tabel', function () {
    $item = StockItem::factory()->create(['stok_saat_ini' => 10]);

    Livewire::actingAs($this->admin)
        ->test(ListStockItems::class)
        ->callTableAction('penyesuaian', $item, data: ['delta' => -4, 'keterangan' => 'Opname']);

    expect($item->fresh()->stok_saat_ini)->toBe(6);
});

it('teknisi tidak bisa akses resource stok (403, tidak boleh masuk panel)', function () {
    expect($this->teknisi->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});

it('kartu stok (ledger) menampilkan movement, read-only', function () {
    StockMovement::factory()->count(3)->create();

    Livewire::actingAs($this->admin)
        ->test(ListStockMovements::class)
        ->assertSuccessful();
});
