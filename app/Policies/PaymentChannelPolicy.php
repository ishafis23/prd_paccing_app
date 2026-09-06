<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\PaymentChannel;
use App\Models\User;

/**
 * Channel pembayaran (B15a/B16a): Admin/Owner mengelola (create/update/delete),
 * Finance hanya boleh melihat (read-only). Owner lolos lewat Gate::before di
 * AuthServiceProvider — tidak perlu disebut di sini.
 */
class PaymentChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::Finance->value]);
    }

    public function view(User $user, PaymentChannel $paymentChannel): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function update(User $user, PaymentChannel $paymentChannel): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public function delete(User $user, PaymentChannel $paymentChannel): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }
}
