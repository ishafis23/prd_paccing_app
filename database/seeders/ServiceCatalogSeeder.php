<?php

namespace Database\Seeders;

use App\Models\ServiceCatalog;
use Illuminate\Database\Seeder;

class ServiceCatalogSeeder extends Seeder
{
    /**
     * Katalog layanan awal (keputusan eksekusi C1).
     * HARGA & interval masih estimasi awal untuk development —
     * wajib dikonfirmasi ke client sebelum go-live.
     */
    public function run(): void
    {
        $items = [
            ['cuci_ac', 'split', '1 PK', 100000, 3],
            ['cuci_ac', 'split', '1.5-2 PK', 150000, 3],
            ['cuci_ac', 'standing', 's/d 3 PK', 200000, 3],
            ['service_ac', 'split', '1 PK', 150000, null],
            ['service_ac', 'split', '1.5-2 PK', 200000, null],
            ['pengadaan_ac', 'split', '1 PK', 3500000, null],
            ['pengadaan_ac', 'split', '1.5 PK', 4200000, null],
            ['pengadaan_ac', 'standing', '2 PK', 6500000, null],
        ];

        foreach ($items as [$jenis, $unit, $pk, $harga, $interval]) {
            ServiceCatalog::updateOrCreate(
                ['jenis_layanan' => $jenis, 'jenis_unit' => $unit, 'pk' => $pk],
                ['harga' => $harga, 'interval_bulan' => $interval, 'aktif' => true]
            );
        }
    }
}
