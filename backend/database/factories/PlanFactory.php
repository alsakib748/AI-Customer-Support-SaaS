<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);
        $monthly = $this->faker->randomElement([0, 29, 79, 199]);

        return [
            'uuid' => (string) Str::uuid(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'price_monthly' => $monthly,
            'price_yearly' => $monthly * 10,
            'currency' => 'USD',
            'trial_days' => $this->faker->randomElement([0, 7, 14, 30]),
            'features' => [
                'ai_chat' => true,
                'knowledge_base' => $monthly > 0,
                'analytics' => $monthly > 0,
                'integrations' => $monthly >= 79,
                'sso' => $monthly >= 199,
            ],
            'limits' => [
                'ai_messages' => $this->faker->randomElement([100, 1000, 10000]),
                'agents' => $this->faker->numberBetween(1, 50),
                'documents' => $this->faker->numberBetween(10, 5000),
                'storage_bytes' => 1073741824,
                'conversations' => $this->faker->numberBetween(50, 50000),
            ],
            'is_active' => true,
            'is_default' => false,
            'is_public' => true,
            'sort_order' => 0,
        ];
    }

    public function free(): static
    {
        return $this->state(fn() => [
            'name' => 'Free',
            'slug' => 'free',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'is_default' => true,
        ]);
    }

    public function enterprise(): static
    {
        return $this->state(fn() => [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'price_monthly' => 199,
            'price_yearly' => 1990,
        ]);
    }

}