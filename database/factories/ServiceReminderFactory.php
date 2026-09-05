<?php

namespace Database\Factories;

use App\Enums\ReminderStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServiceReminder>
 */
class ServiceReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'order_id' => Order::factory(),
            'interval_bulan' => 3,
            'tanggal_servis_berikutnya' => now()->addMonths(3)->toDateString(),
            'status_notice' => ReminderStatus::BelumJatuhTempo,
        ];
    }
}
