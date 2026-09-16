<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Perfect for trying out our service',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'trial_days' => 0,
                'features' => [
                    'ai_chat' => true,
                    'knowledge_base' => false,
                    'ticket_system' => true,
                    'analytics' => false,
                    'integrations' => false,
                    'custom_branding' => false,
                    'priority_support' => false,
                ],
                'limits' => [
                    'ai_messages' => 100,
                    'agents' => 1,
                    'documents' => 10,
                    'storage_bytes' => 104857600, // 100MB
                    'conversations' => 50,
                ],
                'is_default' => true,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 1,
                'color' => 'gray',
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'For small businesses getting started',
                'price_monthly' => 29,
                'price_yearly' => 290,
                'trial_days' => 14,
                'features' => [
                    'ai_chat' => true,
                    'knowledge_base' => true,
                    'ticket_system' => true,
                    'analytics' => true,
                    'integrations' => false,
                    'custom_branding' => true,
                    'priority_support' => false,
                ],
                'limits' => [
                    'ai_messages' => 1000,
                    'agents' => 3,
                    'documents' => 50,
                    'storage_bytes' => 1073741824, // 1GB
                    'conversations' => 500,
                ],
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 2,
                'badge' => 'Popular',
                'color' => 'blue',
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'For growing teams that need more power',
                'price_monthly' => 79,
                'price_yearly' => 790,
                'trial_days' => 14,
                'features' => [
                    'ai_chat' => true,
                    'knowledge_base' => true,
                    'ticket_system' => true,
                    'analytics' => true,
                    'integrations' => true,
                    'custom_branding' => true,
                    'priority_support' => true,
                ],
                'limits' => [
                    'ai_messages' => 10000,
                    'agents' => 10,
                    'documents' => 500,
                    'storage_bytes' => 10737418240, // 10GB
                    'conversations' => 5000,
                ],
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 3,
                'color' => 'purple',
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'For large organizations with custom needs',
                'price_monthly' => 199,
                'price_yearly' => 1990,
                'trial_days' => 30,
                'features' => [
                    'ai_chat' => true,
                    'knowledge_base' => true,
                    'ticket_system' => true,
                    'analytics' => true,
                    'integrations' => true,
                    'custom_branding' => true,
                    'priority_support' => true,
                    'sso' => true,
                    'audit_logs' => true,
                ],
                'limits' => [
                    'ai_messages' => 100000,
                    'agents' => 50,
                    'documents' => 5000,
                    'storage_bytes' => 107374182400, // 100GB
                    'conversations' => 50000,
                ],
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 4,
                'badge' => 'Best Value',
                'color' => 'green',
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                array_merge($plan, [
                    'uuid' => (string) Str::uuid(),
                    'currency' => 'USD',
                ])
            );
        }

        $this->command->info('Plans seeded successfully!');
    }
}