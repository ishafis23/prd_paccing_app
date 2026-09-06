<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\User;

/**
 * Payment ledger read-only di Filament — pencatatan pembayaran wajib lewat
 * aksi "Catat Pembayaran" di OrderResource (memanggil PaymentService::recordPayment)
 * supaya income/reminder/status order tetap konsisten.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::Finance->value]);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }
}
