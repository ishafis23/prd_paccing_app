<?php

namespace Database\Factories;

use App\Enums\UnitType;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerAcUnit>
 */
class CustomerAcUnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'kode_unit' => 'AC-'.fake()->unique()->numerify('###'),
            'kode_ruangan' => fake()->randomElement(['Kelas', 'Ruang Guru', 'Lobby', 'Kantor']).' '.fake()->numberBetween(1, 20),
            'jenis_unit' => fake()->randomElement(UnitType::cases()),
            'pk' => fake()->randomElement(['1/2 PK', '1 PK', '1.5 PK', '2 PK']),
            'catatan' => null,
        ];
    }
}
