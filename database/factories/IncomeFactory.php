<?php

namespace Database\Factories;

use App\Enums\IncomeCategory;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Income>
 */
class IncomeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'kategori' => IncomeCategory::Jasa,
            'nominal' => fake()->numberBetween(50000, 5000000),
            'tanggal' => now()->toDateString(),
            'keterangan' => null,
        ];
    }
}
