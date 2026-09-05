<?php

namespace Database\Factories;

use App\Enums\ServiceType;
use App\Enums\UnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServiceCatalog>
 */
class ServiceCatalogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'jenis_layanan' => ServiceType::CuciAc,
            'jenis_unit' => UnitType::Split,
            'pk' => '1 PK',
            'harga' => 100000,
            'interval_bulan' => 3,
            'aktif' => true,
        ];
    }

    public function pengadaan(): static
    {
        return $this->state(fn () => [
            'jenis_layanan' => ServiceType::PengadaanAc,
            'interval_bulan' => null,
            'harga' => 3500000,
        ]);
    }
}
