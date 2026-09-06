<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\ServiceReminder;
use App\Models\User;

/**
 * Notice servis berikutnya: dibuat otomatis PaymentService saat lunas (B2)
 * atau manual oleh Admin/Owner lewat tombol "Buat Notice Manual" (B35).
 * Finance hanya melihat + aksi "Tandai Dihubungi" (Admin/Owner).
 */
class ServiceReminderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            RoleName::Owner->value,
            RoleName::Admin->value,
            RoleName::Finance->value,
        ]);
    }

    public function view(User $user, ServiceReminder $serviceReminder): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value]);
    }

    public function update(User $user, ServiceReminder $serviceReminder): bool
    {
        return $user->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value]);
    }

    public function delete(User $user, ServiceReminder $serviceReminder): bool
    {
        return false;
    }
}
