<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkReport>
 */
class WorkReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'teknisi_id' => User::factory(),
            'catatan_pengerjaan' => fake()->sentence(),
            'foto_sebelum' => null,
            'foto_sesudah' => null,
            'waktu_mulai' => now()->subHour(),
            'waktu_selesai' => now(),
        ];
    }
}
