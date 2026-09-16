<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'code' => strtoupper($this->faker->unique()->bothify('??##??')),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'type' => 'percentage',
            'value' => $this->faker->randomElement([10, 20, 30, 50]),
            'currency' => 'USD',
            'duration' => 'once',
            'max_redemptions' => 100,
            'times_redeemed' => 0,
            'max_redemptions_per_tenant' => 1,
            'is_active' => true,
        ];
    }

    public function percentage(int $value = 20): static
    {
        return $this->state(fn() => ['type' => 'percentage', 'value' => $value]);
    }

    public function fixedAmount(float $value = 10): static
    {
        return $this->state(fn() => ['type' => 'fixed_amount', 'value' => $value]);
    }

    public function expired(): static
    {
        return $this->state(fn() => ['expires_at' => now()->subDay()]);
    }

    public function inactive(): static
    {
        return $this->state(fn() => ['is_active' => false]);
    }

    public function exhausted(): static
    {
        return $this->state(fn() => [
            'max_redemptions' => 10,
            'times_redeemed' => 10,
        ]);
    }
}