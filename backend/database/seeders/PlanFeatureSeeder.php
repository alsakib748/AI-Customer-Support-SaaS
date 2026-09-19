<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlanFeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $map = [
            'free' => [
                'agents.max' => 2,
                'customers.max' => 500,
                'widgets.max' => 1,
                'kb.articles.max' => 20,
                'conversations.monthly' => 500,
                'ai.requests.monthly' => 100,
                'ai.tokens.monthly' => 50000,
            ],
            'starter' => [
                'agents.max' => 5,
                'customers.max' => 5000,
                'widgets.max' => 2,
                'kb.articles.max' => 100,
                'conversations.monthly' => 5000,
                'ai.requests.monthly' => 2000,
                'ai.tokens.monthly' => 1000000,
            ],
            'professional' => [
                'agents.max' => 20,
                'customers.max' => 25000,
                'widgets.max' => 10,
                'kb.articles.max' => 1000,
                'conversations.monthly' => 50000,
                'ai.requests.monthly' => 10000,
                'ai.tokens.monthly' => 10000000,
            ],
            'enterprise' => [
                'agents.max' => 0, // unlimited
                'customers.max' => 0,
                'widgets.max' => 0,
                'kb.articles.max' => 0,
                'conversations.monthly' => 0,
                'ai.requests.monthly' => 0,
                'ai.tokens.monthly' => 0,
            ],
        ];

        foreach ($map as $slug => $features) {
            $plan = Plan::where('slug', $slug)->first();
            if (!$plan)
                continue;

            foreach ($features as $key => $value) {
                PlanFeature::updateOrCreate(
                    ['plan_id' => $plan->id, 'feature_key' => $key],
                    ['feature_type' => 'limit', 'value' => (string) $value, 'is_enabled' => true]
                );
            }
        }
    }
}