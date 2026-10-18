<?php

namespace Database\Seeders;

use App\Enums\CustomerArea;
use App\Enums\CustomerJenis;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Database\Seeder;

/**
 * Customer bawaan "Internal / Operasional" untuk order misi non-AC (cari part,
 * antar barang, dll.). Tanpa nomor WA sehingga notifikasi customer terlewati.
 * Idempoten — aman dijalankan ulang.
 */
class InternalCustomerSeeder extends Seeder
{
    public const NAMA = 'Internal / Operasional';

    public function run(): void
    {
        Customer::firstOrCreate(
            ['nama' => self::NAMA],
            [
                'jenis' => CustomerJenis::Company,
                'no_hp' => '',
                'area' => CustomerArea::Makassar,
                'status' => CustomerStatus::Aktif,
                'catatan' => 'Customer bawaan untuk misi internal (cari part, antar barang, dll.).',
            ]
        );
    }
}
