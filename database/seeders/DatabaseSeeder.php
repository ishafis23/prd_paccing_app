<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed awal development:
     * - 5 role
     * - katalog layanan awal
     * - akun demo (password semua: 'password')
     *
     * Akun demo hanya untuk development lokal, bukan data produksi.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            ServiceCatalogSeeder::class,
        ]);

        $owner = User::updateOrCreate(
            ['email' => 'owner@paccing.test'],
            ['name' => 'Owner Paccing', 'phone' => '081100000001', 'password' => 'password']
        );
        // Owner dirangkap semua role backoffice (keputusan eksekusi B7).
        $owner->syncRoles([
            RoleName::Owner->value,
            RoleName::Admin->value,
            RoleName::Finance->value,
            RoleName::Hr->value,
        ]);

        $admin = User::updateOrCreate(
            ['email' => 'admin@paccing.test'],
            ['name' => 'Admin Paccing', 'phone' => '081100000002', 'password' => 'password']
        );
        $admin->syncRoles([RoleName::Admin->value]);

        foreach (['Teknisi Andi', 'Teknisi Budi'] as $i => $nama) {
            $teknisi = User::updateOrCreate(
                ['email' => 'teknisi' . ($i + 1) . '@paccing.test'],
                ['name' => $nama, 'phone' => '08110000000' . (3 + $i), 'password' => 'password']
            );
            $teknisi->syncRoles([RoleName::Teknisi->value]);
        }
    }
}
