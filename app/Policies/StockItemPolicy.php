<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\StockItem;
use App\Models\User;

class StockItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::Finance->value]);
    }

    public function view(User $user, StockItem $stockItem): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function update(User $user, StockItem $stockItem): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function delete(User $user, StockItem $stockItem): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }
}
