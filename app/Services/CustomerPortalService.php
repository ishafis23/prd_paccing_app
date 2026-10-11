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

    /**
     * Atur akses Portal (Plan A, dev-plan/22): username (opsional) + password.
     * Bila username kosong, email dipakai sebagai identitas login. Minimal
     * salah satu (username/email) harus ada.
     */
    public function aturAkses(Customer $customer, ?string $username, string $password, User $actor): Customer
    {
        $this->assertRole($actor, [RoleName::Admin, RoleName::Owner]);

        if (mb_strlen($password) < 8) {
            throw new BusinessRuleException('Password minimal 8 karakter.');
        }

        $username = trim((string) $username);

        if ($username === '' && blank($customer->email)) {
            throw new BusinessRuleException('Customer harus punya username atau email sebelum diberi akses Portal.');
        }

        if ($username !== '') {
            $sudahDipakai = Customer::query()
                ->whereKeyNot($customer->id)
                ->whereRaw('lower(username) = ?', [strtolower($username)])
                ->whereNotNull('password')
                ->exists();

            if ($sudahDipakai) {
                throw new BusinessRuleException('Username ini sudah dipakai customer lain yang juga punya akses Portal — pakai username lain.');
            }

            $customer->username = $username;
        }

        $email = trim((string) $customer->email);

        if ($email !== '') {
            $emailDipakai = Customer::query()
                ->whereKeyNot($customer->id)
                ->whereRaw('lower(email) = ?', [strtolower($email)])
                ->whereNotNull('password')
                ->exists();

            if ($emailDipakai) {
                throw new BusinessRuleException('Email ini sudah dipakai customer lain yang juga punya akses Portal — pakai email lain.');
            }
        }

        $customer->password = $password;
        $customer->save();

        return $customer->fresh();
    }

    /**
     * Kompatibilitas: atur password memakai email sebagai identitas login.
     */
    public function aturPassword(Customer $customer, string $password, User $actor): Customer
    {
        return $this->aturAkses($customer, null, $password, $actor);
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
