<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Membuat 5 role sesuai PRD §3. Fase 1 tanpa permission granular:
     * otorisasi berbasis role (spatie). Owner = super admin via Gate::before
     * yang didaftarkan di AppServiceProvider.
     */
    public function run(): void
    {
        foreach (RoleName::cases() as $role) {
            Role::findOrCreate($role->value);
        }
    }
}
