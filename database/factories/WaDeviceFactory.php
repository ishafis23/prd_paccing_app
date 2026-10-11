<?php

namespace Database\Factories;

use App\Models\ProviderAccount;
use App\Models\WaDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaDevice>
 */
class WaDeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider_account_id' => ProviderAccount::factory(),
            'device_name' => 'device'.fake()->unique()->numberBetween(1, 99999),
            'nomor_wa' => '62812'.fake()->numerify('#######'),
            'status' => WaDevice::STATUS_TERSEDIA,
            'connection_status' => WaDevice::KONEKSI_BELUM,
            'assigned_user_id' => null,
            'last_checked_at' => null,
        ];
    }
}
