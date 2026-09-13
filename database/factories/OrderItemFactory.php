<?php

namespace Database\Factories;

use App\Enums\ServiceType;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'service_catalog_id' => null,
            'nama_layanan' => fake()->randomElement(['Cuci AC', 'Ganti Kapasitor', 'Tambah Freon']),
            'kategori' => ServiceType::CuciAc,
            'harga' => fake()->randomElement([75000, 100000, 150000]),
            'jumlah' => 1,
            'catatan' => null,
            'ditambahkan_oleh' => null,
        ];
    }
}
