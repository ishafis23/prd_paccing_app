<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::Finance->value, RoleName::Hr->value]);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }
}
