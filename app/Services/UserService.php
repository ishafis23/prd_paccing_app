<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Kelola akun pengguna panel (keputusan B20).
 *
 * Aturan:
 * - Pembuat/pengubah: Admin & Owner.
 * - Hanya Owner yang bisa membuat/mengubah akun ber-role Owner.
 * - Admin tidak bisa mengubah/menonaktifkan akun sendiri maupun akun Owner.
 * - Role akun sendiri tidak bisa diubah (siapa pun).
 * - Akun tidak pernah dihapus; gunakan status nonaktif.
 */
class UserService
{
    use RestrictsByRole;

    /**
     * @param  array{name: string, email: string, phone?: ?string, password: string, role: string, status?: string}  $data
     */
    public function createUser(array $data, User $by): User
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Owner]);

        $role = $this->resolusiRole($data['role'] ?? null);
        $this->pastikanBisaMemberiRole($role, $by);

        $email = $this->normalisasiEmail($data['email'] ?? '');
        if (User::query()->where('email', $email)->exists()) {
            throw new BusinessRuleException('Email sudah digunakan.');
        }

        $password = (string) ($data['password'] ?? '');
        if (mb_strlen($password) < 8) {
            throw new BusinessRuleException('Password minimal 8 karakter.');
        }

        $user = User::create([
            'name' => trim((string) ($data['name'] ?? '')),
            'email' => $email,
            'phone' => $this->nullJikaKosong($data['phone'] ?? null),
            'password' => $password, // cast hashed
            'status' => UserStatus::tryFrom((string) ($data['status'] ?? '')) ?? UserStatus::Aktif,
        ]);
        $user->syncRoles([$role->value]);

        return $user->fresh();
    }

    /**
     * @param  array{name?: string, email?: string, phone?: ?string, password?: ?string, role?: ?string, status?: ?string}  $data
     */
    public function updateUser(User $user, array $data, User $by): User
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Owner]);
        $this->pastikanBolehKelola($user, $by);

        $role = null;
        if (! empty($data['role'])) {
            $role = $this->resolusiRole($data['role']);
            $this->pastikanBisaMemberiRole($role, $by);

            if ($user->id === $by->id) {
                throw new BusinessRuleException('Role akun sendiri tidak bisa diubah.');
            }
        }

        $email = $this->normalisasiEmail($data['email'] ?? $user->email);
        $duplikat = User::query()->where('email', $email)->where('id', '!=', $user->id)->exists();
        if ($duplikat) {
            throw new BusinessRuleException('Email sudah digunakan.');
        }

        if (! empty($data['status'])) {
            $status = UserStatus::tryFrom((string) $data['status'])
                ?? throw new BusinessRuleException('Status tidak valid.');

            if ($status === UserStatus::Nonaktif && $user->id === $by->id && ! $by->isOwner()) {
                throw new AuthorizationException('Anda tidak bisa menonaktifkan akun sendiri.');
            }

            $user->status = $status;
        }

        $password = (string) ($data['password'] ?? '');
        if ($password !== '') {
            if (mb_strlen($password) < 8) {
                throw new BusinessRuleException('Password minimal 8 karakter.');
            }
            $user->password = $password; // cast hashed
        }

        $user->name = trim((string) ($data['name'] ?? $user->name));
        $user->email = $email;
        $user->phone = array_key_exists('phone', $data)
            ? $this->nullJikaKosong($data['phone'])
            : $user->phone;

        if ($role !== null) {
            $user->syncRoles([$role->value]);
        }

        $user->save();

        return $user->fresh();
    }

    public function resetPassword(User $user, string $password, User $by): User
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Owner]);
        $this->pastikanBolehKelola($user, $by);

        if (mb_strlen($password) < 8) {
            throw new BusinessRuleException('Password minimal 8 karakter.');
        }

        $user->password = $password;
        $user->save();

        return $user->fresh();
    }

    private function resolusiRole(mixed $role): RoleName
    {
        return RoleName::tryFrom((string) $role)
            ?? throw new BusinessRuleException('Role tidak valid.');
    }

    private function pastikanBisaMemberiRole(RoleName $role, User $by): void
    {
        if ($role === RoleName::Owner && ! $by->isOwner()) {
            throw new AuthorizationException('Hanya Owner yang dapat mengelola akun Owner.');
        }
    }

    private function pastikanBolehKelola(User $user, User $by): void
    {
        if ($user->isOwner() && ! $by->isOwner()) {
            throw new AuthorizationException('Hanya Owner yang dapat mengelola akun Owner.');
        }

        if ($user->id === $by->id && ! $by->isOwner()) {
            throw new AuthorizationException('Anda tidak dapat mengubah akun sendiri.');
        }
    }

    private function normalisasiEmail(mixed $email): string
    {
        $email = strtolower(trim((string) $email));

        if ($email === '') {
            throw new BusinessRuleException('Email wajib diisi.');
        }

        return $email;
    }

    private function nullJikaKosong(mixed $nilai): ?string
    {
        $nilai = is_string($nilai) ? trim($nilai) : $nilai;

        return ($nilai === null || $nilai === '') ? null : (string) $nilai;
    }
}
