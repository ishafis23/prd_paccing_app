<?php

namespace Database\Factories;

use App\Models\BerandaSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BerandaSetting>
 */
class BerandaSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'maps_embed' => null,
            'jam_operasional' => 'Senin–Sabtu, 08.00–17.00',
            'sosmed_instagram' => null,
            'sosmed_facebook' => null,
            'tampil_layanan' => true,
            'tampil_cara_kerja' => true,
            'tampil_area' => true,
            'tampil_peta' => true,
        ];
    }
}
