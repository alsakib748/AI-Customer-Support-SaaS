<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantUser>
 */
class TenantUserFactory extends Factory
{
    protected $model = \App\Models\TenantUser::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'role' => $this->faker->randomElement(['owner', 'admin', 'manager', 'agent', 'viewer']),
            'department' => $this->faker->randomElement([
                'Customer Support',
                'Technical Support',
                'Sales',
                'Marketing',
                'Engineering',
                'Operations',
                'Finance',
                'Human Resources',
            ]),
            'position' => $this->faker->jobTitle(),
            'availability_status' => $this->faker->randomElement(['online', 'offline', 'away', 'busy']),
            'max_concurrent_chats' => $this->faker->numberBetween(1, 10),
            'skills' => $this->faker->randomElements([
                'Billing',
                'Technical Support',
                'Sales',
                'Account Management',
                'Product Knowledge',
                'Customer Service',
                'Troubleshooting',
                'Escalation Handling',
            ], 3),
            'invited_at' => null,
            'accepted_at' => now(),
        ];
    }

    public function owner()
    {
        return $this->state(function (array $attributes) {
            return [
                'role' => 'owner',
            ];
        });
    }

    public function admin()
    {
        return $this->state(function (array $attributes) {
            return [
                'role' => 'admin',
            ];
        });
    }

    public function agent()
    {
        return $this->state(function (array $attributes) {
            return [
                'role' => 'agent',
            ];
        });
    }

    public function online()
    {
        return $this->state(function (array $attributes) {
            return [
                'availability_status' => 'online',
            ];
        });
    }

    public function offline()
    {
        return $this->state(function (array $attributes) {
            return [
                'availability_status' => 'offline',
            ];
        });
    }
}
