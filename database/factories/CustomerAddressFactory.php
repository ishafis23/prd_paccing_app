<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'nama_lokasi' => fake()->randomElement(['Rumah', 'Rumah 2', 'Kantor', 'Usaha']),
            'alamat' => fake()->address(),
            'maps_link' => null,
            'latitude' => null,
            'longitude' => null,
            'is_utama' => false,
            'catatan' => null,
        ];
    }
}
