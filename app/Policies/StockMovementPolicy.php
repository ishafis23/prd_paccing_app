<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\StockMovement;
use App\Models\User;

/** Kartu stok read-only di Filament — perubahan stok wajib lewat StockService (aksi Stok Masuk/Penyesuaian di StockItemResource, atau otomatis dari laporan Teknisi). */
class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::Finance->value]);
    }

    public function view(User $user, StockMovement $stockMovement): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StockMovement $stockMovement): bool
    {
        return false;
    }

    public function delete(User $user, StockMovement $stockMovement): bool
    {
        return false;
    }
}
