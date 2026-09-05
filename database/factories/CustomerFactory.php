<?php

namespace Database\Factories;

use App\Enums\CustomerArea;
use App\Enums\CustomerStatus;
use App\Enums\LeadSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'no_hp' => '08' . fake()->numerify('##########'),
            'alamat' => fake()->address(),
            'area' => fake()->randomElement(CustomerArea::cases()),
            'sumber_lead' => fake()->randomElement(LeadSource::cases()),
            'status' => CustomerStatus::Lead,
            'catatan' => null,
        ];
    }
}
