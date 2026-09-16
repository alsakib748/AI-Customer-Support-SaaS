<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 29, 500);

        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'subscription_id' => Subscription::factory(),
            'invoice_number' => 'INV-' . date('Ym') . '-' . str_pad($this->faker->unique()->numberBetween(1, 99999), 6, '0', STR_PAD_LEFT),
            'subtotal' => $subtotal,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => $subtotal,
            'currency' => 'USD',
            'status' => 'open',
            'due_at' => now()->addDays(7),
            'line_items' => [
                [
                    'description' => 'Subscription',
                    'quantity' => 1,
                    'unit_price' => $subtotal,
                    'total' => $subtotal,
                ],
            ],
        ];
    }

    public function paid(): static
    {
        return $this->state(fn() => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn() => [
            'status' => 'open',
            'due_at' => now()->subDays(3),
        ]);
    }
}