<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'stock_item_id' => StockItem::factory(),
            'jenis' => MovementType::Masuk,
            'jumlah' => fake()->numberBetween(1, 20),
            'referensi' => null,
            'keterangan' => null,
            'dicatat_oleh' => User::factory(),
            'tanggal' => now()->toDateString(),
        ];
    }
}
