<?php

use App\Enums\MovementType;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->stockService = new StockService;
});

function adminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin->value);

    return $user;
}

it('stok masuk membuat movement dan menambah stok_saat_ini', function () {
    $admin = adminUser();
    $item = StockItem::factory()->create(['stok_saat_ini' => 5]);

    $this->stockService->masuk($item, 10, $admin, 'Pembelian bulanan');

    expect($item->fresh()->stok_saat_ini)->toBe(15);

    $movement = $item->fresh()->movements()->latest('id')->first();
    expect($movement->jenis)->toBe(MovementType::Masuk)
        ->and($movement->jumlah)->toBe(10)
        ->and($movement->dicatat_oleh)->toBe($admin->id)
        ->and($movement->keterangan)->toBe('Pembelian bulanan');
});

it('stok masuk dengan jumlah <= 0 ditolak', function () {
    $admin = adminUser();
    $item = StockItem::factory()->create();

    $this->stockService->masuk($item, 0, $admin);
})->throws(BusinessRuleException::class);

it('stok keluar mengurangi stok dan boleh minus (keputusan B4)', function () {
    $admin = adminUser();
    $item = StockItem::factory()->create(['stok_saat_ini' => 2]);

    $this->stockService->keluar($item, 5, $admin, 'work_report:1', 'Dipakai laporan');

    expect($item->fresh()->stok_saat_ini)->toBe(-3);

    $movement = $item->fresh()->movements()->latest('id')->first();
    expect($movement->jenis)->toBe(MovementType::Keluar)
        ->and($movement->referensi)->toBe('work_report:1');
});

it('penyesuaian negatif mengurangi stok dan tersimpan bertanda', function () {
    $admin = adminUser();
    $item = StockItem::factory()->create(['stok_saat_ini' => 10]);

    $this->stockService->penyesuaian($item, -4, $admin, 'Opname fisik');

    expect($item->fresh()->stok_saat_ini)->toBe(6);

    $movement = $item->fresh()->movements()->latest('id')->first();
    expect($movement->jenis)->toBe(MovementType::Penyesuaian)
        ->and($movement->jumlah)->toBe(-4);
});

it('teknisi tidak boleh restock stok', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    $item = StockItem::factory()->create();

    $this->stockService->masuk($item, 10, $teknisi);
})->throws(Illuminate\Auth\Access\AuthorizationException::class);
