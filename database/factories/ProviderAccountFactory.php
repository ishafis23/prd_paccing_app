<?php

namespace Database\Factories;

use App\Models\ProviderAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderAccount>
 */
class ProviderAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'label' => 'Gateway '.fake()->unique()->numberBetween(1, 999),
            'iduser' => (string) fake()->unique()->numberBetween(100, 999999),
            'base_url_qr' => null,
            'base_url_send' => null,
            'base_url_group' => null,
            'base_url_document' => null,
        ];
    }
}
