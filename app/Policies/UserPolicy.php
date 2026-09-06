<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

/**
 * Akses resource Pengguna (B20): Admin & Owner (Owner lolos Gate::before).
 * Aturan owner/self di level layanan (UserService) — policy ini gerbang kasar.
 */
class UserPolicy
{
    private const ALLOWED = [RoleName::Admin->value, RoleName::Owner->value];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(self::ALLOWED);
    }

    public function view(User $user, User $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::ALLOWED);
    }

    public function update(User $user, User $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, User $record): bool
    {
        // B20: akun tidak pernah dihapus — gunakan status nonaktif.
        return false;
    }
}
