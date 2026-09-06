<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    private const ALLOWED = [RoleName::Admin->value, RoleName::Finance->value];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(self::ALLOWED);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::ALLOWED);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->hasAnyRole(self::ALLOWED);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->hasAnyRole(self::ALLOWED);
    }
}
