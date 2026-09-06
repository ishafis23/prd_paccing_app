<?php

namespace Database\Factories;

use App\Models\HeroSlide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HeroSlide>
 */
class HeroSlideFactory extends Factory
{
    public function definition(): array
    {
        return [
            'judul' => fake()->sentence(5),
            'subjudul' => fake()->paragraph(),
            'gambar' => 'hero/'.fake()->uuid().'.jpg',
            'tombol_teks' => null,
            'tombol_url' => null,
            'urutan' => 0,
            'aktif' => true,
        ];
    }
}
