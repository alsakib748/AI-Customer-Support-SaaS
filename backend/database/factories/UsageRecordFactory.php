<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\UsageRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageRecord>
 */
class UsageRecordFactory extends Factory
{
    protected $model = UsageRecord::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'subscription_id' => Subscription::factory(),
            'type' => $this->faker->randomElement(['ai', 'agents', 'documents', 'conversations']),
            'quantity' => $this->faker->numberBetween(1, 100),
            'unit' => 'count',
            'period_date' => now()->toDateString(),
            'period_type' => 'daily',
            'cost' => 0,
        ];
    }

    public function ai(): static
    {
        return $this->state(fn() => ['type' => 'ai']);
    }

    public function monthly(): static
    {
        return $this->state(fn() => ['period_type' => 'monthly']);
    }
}