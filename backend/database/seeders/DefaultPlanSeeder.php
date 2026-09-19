<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DefaultPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plan = Plan::updateOrCreate(
            ['slug' => 'free'],
            [
                'uuid'          => (string) Str::uuid(),
                'name'          => 'Free',
                'description'   => 'Perfect for getting started',
                'price_monthly' => 0,
                'price_yearly'  => 0,
                'currency'      => 'USD',
                'trial_days'    => 14,
                'is_active'     => true,
                'is_default'    => true,
                'is_public'     => true,
                'sort_order'    => 1,
            ]
        );

        $limits = [
            'agents.max'            => 2,
            'customers.max'         => 500,
            'widgets.max'           => 1,
            'kb.articles.max'       => 20,
            'conversations.monthly' => 500,
            'ai.requests.monthly'   => 100,
            'ai.tokens.monthly'     => 50000,
            'storage.bytes'         => 1073741824,
        ];

        foreach ($limits as $key => $value) {
            PlanFeature::updateOrCreate(
                ['plan_id' => $plan->id, 'feature_key' => $key],
                ['feature_type' => 'limit', 'value' => (string) $value, 'is_enabled' => true]
            );
        }
    }
}
