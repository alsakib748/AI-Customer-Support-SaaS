<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create a tenant
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo-workspace'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Demo Workspace',
                'slug' => 'demo-workspace',
                'status' => 'active',
            ]
        );

        // Create owner
        $owner = User::firstOrCreate(
            ['email' => 'owner@example.com'],
            [
                'first_name' => 'Owner',
                'last_name' => 'User',
                'email' => 'owner@example.com',
                'password' => bcrypt('password'),
                'uuid' => (string) Str::uuid(),
                'is_active' => true,
            ]
        );

        TenantUser::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'user_id' => $owner->id,
            ],
            [
                'role' => 'owner',
                'department' => 'Management',
                'position' => 'Owner',
                'availability_status' => 'online',
                'max_concurrent_chats' => 10,
                'skills' => ['Leadership', 'Strategy'],
                'accepted_at' => now(),
            ]
        );

        // Create admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'uuid' => (string) Str::uuid(),
                'is_active' => true,
            ]
        );

        TenantUser::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'user_id' => $admin->id,
            ],
            [
                'role' => 'admin',
                'department' => 'Management',
                'position' => 'Administrator',
                'availability_status' => 'online',
                'max_concurrent_chats' => 8,
                'skills' => ['Management', 'Operations'],
                'accepted_at' => now(),
            ]
        );

        // Create agents
        $agents = [
            ['email' => 'agent1@example.com', 'first_name' => 'Agent', 'last_name' => 'One'],
            ['email' => 'agent2@example.com', 'first_name' => 'Agent', 'last_name' => 'Two'],
            ['email' => 'agent3@example.com', 'first_name' => 'Agent', 'last_name' => 'Three'],
        ];

        foreach ($agents as $agentData) {
            $user = User::firstOrCreate(
                ['email' => $agentData['email']],
                [
                    'first_name' => $agentData['first_name'],
                    'last_name' => $agentData['last_name'],
                    'email' => $agentData['email'],
                    'password' => bcrypt('password'),
                    'uuid' => (string) Str::uuid(),
                    'is_active' => true,
                ]
            );

            TenantUser::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => 'agent',
                    'department' => $this->faker->randomElement(['Customer Support', 'Technical Support']),
                    'position' => 'Support Agent',
                    'availability_status' => $this->faker->randomElement(['online', 'away', 'busy']),
                    'max_concurrent_chats' => 5,
                    'skills' => ['Billing', 'Technical Support', 'Customer Service'],
                    'accepted_at' => now(),
                ]
            );
        }

        // Create invitations
        $invitations = [
            ['email' => 'pending1@example.com', 'role' => 'agent'],
            ['email' => 'pending2@example.com', 'role' => 'manager'],
        ];

        foreach ($invitations as $invite) {
            TenantInvitation::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'email' => $invite['email'],
                    'accepted_at' => null,
                ],
                [
                    'invited_by' => $owner->id,
                    'email' => $invite['email'],
                    'role' => $invite['role'],
                    'department' => 'Customer Support',
                    'token' => Str::random(64),
                    'expires_at' => now()->addDays(7),
                ]
            );
        }

        $this->command->info('Team seeded successfully!');
        $this->command->info('Owner: owner@example.com / password');
        $this->command->info('Admin: admin@example.com / password');
        $this->command->info('Agents: agent1@example.com, agent2@example.com, agent3@example.com / password');
    }

    protected $faker;

    public function __construct()
    {
        $this->faker = \Faker\Factory::create();
    }
}
