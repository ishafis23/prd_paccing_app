<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\CustomerAcUnit;
use App\Models\User;

class CustomerAcUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::Finance->value, RoleName::Hr->value]);
    }

    public function view(User $user, CustomerAcUnit $unit): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function update(User $user, CustomerAcUnit $unit): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function delete(User $user, CustomerAcUnit $unit): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }
}
