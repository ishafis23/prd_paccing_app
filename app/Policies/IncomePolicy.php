<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Income;
use App\Models\User;

/** Dibuat otomatis oleh PaymentService saat order lunas — read-only di Filament. */
class IncomePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::Finance->value]);
    }

    public function view(User $user, Income $income): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Income $income): bool
    {
        return false;
    }

    public function delete(User $user, Income $income): bool
    {
        return false;
    }
}
