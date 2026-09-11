<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\AIConfiguration;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AIConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AIConfiguration::firstOrCreate(
            [],
            [
                'provider' => 'gemini',
                'model' => 'gemini-1.5-flash',
                'enabled' => true,
                'auto_reply_enabled' => true,
                'auto_escalation_enabled' => true,
                'streaming_enabled' => true,
                'knowledge_base_enabled' => true,
                'temperature' => 0.7,
                'max_tokens' => 2000,
                'system_prompt' => null,
                'custom_instructions' => null,
                'settings' => [],
            ]
        );

        $this->command->info('AI Configuration seeded successfully with Gemini!');
    }
}