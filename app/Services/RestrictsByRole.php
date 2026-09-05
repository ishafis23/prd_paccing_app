<?php

namespace App\Services;

use App\Enums\RoleName;
use Illuminate\Auth\Access\AuthorizationException;
use App\Models\User;

trait RestrictsByRole
{
    /**
     * Pastikan user memegang minimal satu role yang diizinkan.
     *
     * @throws AuthorizationException
     */
    protected function assertRole(User $user, array $roles): void
    {
        $allowed = array_map(fn (RoleName $r) => $r->value, $roles);

        if (! $user->hasAnyRole($allowed)) {
            throw new AuthorizationException('Role tidak berhak melakukan aksi ini.');
        }
    }
}
