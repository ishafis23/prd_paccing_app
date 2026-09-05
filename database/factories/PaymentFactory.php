<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        $order = Order::factory()->create();

        return [
            'order_id' => $order,
            'metode' => fake()->randomElement(PaymentMethod::cases()),
            'status' => PaymentStatus::BelumBayar,
            'total_tagihan' => $order->total(),
            'jumlah_dibayar' => 0,
            'tanggal_bayar' => null,
            'dicatat_oleh' => User::factory(),
        ];
    }

    public function lunas(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Lunas,
            'jumlah_dibayar' => fn ($attr) => $attr['total_tagihan'] ?? 0,
            'tanggal_bayar' => now()->toDateString(),
        ]);
    }
}
