<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kategori' => fake()->randomElement(ExpenseCategory::cases()),
            'nominal' => fake()->numberBetween(10000, 2000000),
            'tanggal' => now()->toDateString(),
            'keterangan' => fake()->sentence(),
            'bukti' => null,
            'dicatat_oleh' => User::factory(),
        ];
    }
}
