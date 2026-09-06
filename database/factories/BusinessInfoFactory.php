<?php

namespace Database\Factories;

use App\Models\BusinessInfo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BusinessInfo>
 */
class BusinessInfoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama_usaha' => 'Paccing Official',
            'alamat' => null,
            'kontak_wa' => null,
            'email' => null,
            'nama_pemilik' => null,
            'logo_path' => null,
            'diubah_oleh' => null,
        ];
    }
}
