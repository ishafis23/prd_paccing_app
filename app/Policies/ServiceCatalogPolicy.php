<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\ServiceCatalog;
use App\Models\User;

class ServiceCatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // semua role backoffice perlu lihat katalog saat buat order
    }

    public function view(User $user, ServiceCatalog $serviceCatalog): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function update(User $user, ServiceCatalog $serviceCatalog): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function delete(User $user, ServiceCatalog $serviceCatalog): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }
}
