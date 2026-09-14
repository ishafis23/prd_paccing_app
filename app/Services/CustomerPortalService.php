<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\User;

/**
 * Aktivasi/nonaktivasi akses Portal Customer (dev-plan/12 §3.6) — 1 akun
 * login per customer (keputusan 13 Sept), pakai `customers.email` +
 * `customers.password` yg baru. Admin yg mengelola, bukan self-service
 * registrasi customer.
 */
class CustomerPortalService
{
    use RestrictsByRole;

    public function aturPassword(Customer $customer, string $password, User $actor): Customer
    {
        $this->assertRole($actor, [RoleName::Admin, RoleName::Owner]);

        if (blank($customer->email)) {
            throw new BusinessRuleException('Customer harus punya email dulu sebelum diberi akses Portal.');
        }

        if (mb_strlen($password) < 8) {
            throw new BusinessRuleException('Password minimal 8 karakter.');
        }

        $sudahDipakai = Customer::query()
            ->whereKeyNot($customer->id)
            ->whereRaw('lower(email) = ?', [strtolower($customer->email)])
            ->whereNotNull('password')
            ->exists();

        if ($sudahDipakai) {
            throw new BusinessRuleException('Email ini sudah dipakai customer lain yang juga punya akses Portal — pakai email lain.');
        }

        $customer->password = $password;
        $customer->save();

        return $customer->fresh();
    }

    /**
     * Cabut akses Portal (mis. kontrak berakhir) — data customer TIDAK
     * dihapus, cuma tidak bisa login lagi sampai diaktifkan ulang.
     */
    public function cabutAkses(Customer $customer, User $actor): Customer
    {
        $this->assertRole($actor, [RoleName::Admin, RoleName::Owner]);

        $customer->password = null;
        $customer->save();

        return $customer->fresh();
    }
}
