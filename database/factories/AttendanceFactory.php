<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_id' => null,
            'tanggal' => now()->toDateString(),
            'jam_masuk' => now(),
            'jam_keluar' => null,
            'lokasi' => null,
            'status' => AttendanceStatus::Hadir,
        ];
    }
}
