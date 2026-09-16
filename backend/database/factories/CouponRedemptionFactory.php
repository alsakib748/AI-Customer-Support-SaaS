<?php

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CouponRedemption>
 */
class CouponRedemptionFactory extends Factory
{
    protected $model = CouponRedemption::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coupon_id' => Coupon::factory(),
            'tenant_id' => Tenant::factory(),
            'subscription_id' => Subscription::factory(),
            'discount_amount' => $this->faker->randomFloat(2, 5, 50),
            'currency' => 'USD',
            'redeemed_at' => now(),
        ];
    }
}