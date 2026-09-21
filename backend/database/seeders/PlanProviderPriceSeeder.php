<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanProviderPrice;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlanProviderPriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Example mapping — replace provider_price_id with actual IDs from
        // your Stripe Dashboard and PayPal Dashboard.
        $map = [
            'starter' => [
                'stripe' => [
                    'monthly' => 'price_REPLACE_STARTER_MONTHLY',
                    'yearly'  => 'price_REPLACE_STARTER_YEARLY',
                ],
                'paypal' => [
                    'monthly' => 'P-REPLACE_STARTER_MONTHLY',
                    'yearly'  => 'P-REPLACE_STARTER_YEARLY',
                ],
            ],
            'professional' => [
                'stripe' => [
                    'monthly' => 'price_REPLACE_PRO_MONTHLY',
                    'yearly'  => 'price_REPLACE_PRO_YEARLY',
                ],
                'paypal' => [
                    'monthly' => 'P-REPLACE_PRO_MONTHLY',
                    'yearly'  => 'P-REPLACE_PRO_YEARLY',
                ],
            ],
        ];

        foreach ($map as $slug => $providers) {
            $plan = Plan::where('slug', $slug)->first();
            if (!$plan) continue;

            foreach ($providers as $provider => $cycles) {
                foreach ($cycles as $cycle => $priceId) {
                    PlanProviderPrice::updateOrCreate(
                        [
                            'plan_id'       => $plan->id,
                            'provider'      => $provider,
                            'billing_cycle' => $cycle,
                            'currency'      => 'USD',
                        ],
                        [
                            'provider_price_id' => $priceId,
                            'amount'            => $cycle === 'yearly'
                                ? $plan->price_yearly
                                : $plan->price_monthly,
                            'is_active'         => true,
                        ]
                    );
                }
            }
        }

        $this->command->info('Provider prices seeded. Update the REPLACE placeholders with real IDs.');
    }
}
