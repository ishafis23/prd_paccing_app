<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'service_catalog_id' => ServiceCatalog::factory(),
            'teknisi_id' => null,
            'jumlah_unit' => 1,
            'alamat_pengerjaan' => fake()->address(),
            'tanggal_jadwal' => now()->addDay()->toDateString(),
            'jam_jadwal' => '09:00',
            'status' => OrderStatus::Baru,
            'catatan_admin' => null,
            'created_by' => User::factory(),
        ];
    }

    public function terjadwal(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Terjadwal,
            'teknisi_id' => User::factory(),
        ]);
    }
}
