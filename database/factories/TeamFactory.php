<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => 'Tim '.fake()->unique()->city(),
            'aktif' => true,
            'pic_teknisi_id' => null,
        ];
    }
}
