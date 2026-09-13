<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function view(User $user, Team $team): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function update(User $user, Team $team): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }
}
