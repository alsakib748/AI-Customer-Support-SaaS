<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'invoice_id' => Invoice::factory(),
            'payment_id' => 'pay_' . Str::random(24),
            'amount' => $this->faker->randomFloat(2, 29, 500),
            'currency' => 'USD',
            'status' => 'completed',
            'provider' => 'stripe',
            'payment_method' => 'card',
            'last_four' => $this->faker->numerify('####'),
            'card_brand' => $this->faker->randomElement(['visa', 'mastercard', 'amex']),
            'paid_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn() => [
            'status' => 'failed',
            'failure_reason' => 'Card declined',
            'paid_at' => null,
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn() => [
            'status' => 'refunded',
            'refunded_amount' => $this->faker->randomFloat(2, 29, 500),
            'refunded_at' => now(),
        ]);
    }
}