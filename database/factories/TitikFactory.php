<?php

namespace Database\Factories;

use App\Models\Titik;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Titik>
 */
class TitikFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => 'Titik '.fake()->unique()->numberBetween(1, 999),
            'jam' => fake()->time('H:i:s'),
            'urutan' => 0,
            'aktif' => true,
        ];
    }
}
