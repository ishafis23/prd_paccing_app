<?php

namespace Database\Factories;

use App\Enums\StockCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockItem>
 */
class StockItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama_barang' => fake()->randomElement(['Freon R32', 'Kapasitor 25uF', 'Kabel 1.5mm', 'Filter AC']),
            'kategori' => fake()->randomElement(StockCategory::cases()),
            'satuan' => 'pcs',
            'stok_saat_ini' => 10,
            'stok_minimum' => 2,
            'harga_beli' => null,
            'aktif' => true,
        ];
    }
}
