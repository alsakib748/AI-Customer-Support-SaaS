<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionItem>
 */
class SubscriptionItemFactory extends Factory
{
    protected $model = SubscriptionItem::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 10);
        $unitPrice = $this->faker->randomFloat(2, 5, 50);

        return [
            'subscription_id' => Subscription::factory(),
            'type' => $this->faker->randomElement(['addon', 'seat', 'usage']),
            'name' => $this->faker->words(2, true),
            'slug' => $this->faker->slug(),
            'description' => $this->faker->sentence(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,
        ];
    }

    public function addon(): static
    {
        return $this->state(fn() => [
            'type' => 'addon',
            'name' => 'Extra Storage',
        ]);
    }

    public function seat(): static
    {
        return $this->state(fn() => [
            'type' => 'seat',
            'name' => 'Additional Agent Seat',
            'unit_price' => 10,
        ]);
    }
}