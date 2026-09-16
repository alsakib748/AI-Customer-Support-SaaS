<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = now();

        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => $now,
            'ends_at' => $now->copy()->addMonth(),
            'auto_renew' => true,
            'next_billing_at' => $now->copy()->addMonth(),
            'ai_limit' => 1000,
            'agents_limit' => 5,
            'documents_limit' => 100,
            'storage_limit' => 1073741824,
            'conversations_limit' => 500,
        ];
    }

    public function trialing(): static
    {
        return $this->state(fn() => [
            'status' => 'trialing',
            'trial_starts_at' => now(),
            'trial_ends_at' => now()->addDays(14),
            'ends_at' => now()->addDays(14),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn() => [
            'status' => 'expired',
            'ends_at' => now()->subDay(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn() => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'auto_renew' => false,
        ]);
    }
}