<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TenantInvitation>
 */
class TenantInvitationFactory extends Factory
{
    protected $model = \App\Models\TenantInvitation::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'invited_by' => User::factory(),
            'email' => $this->faker->email(),
            'role' => $this->faker->randomElement(['admin', 'manager', 'agent', 'viewer']),
            'department' => $this->faker->randomElement([
                'Customer Support',
                'Technical Support',
                'Sales',
                'Marketing',
            ]),
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'revoked_at' => null,
        ];
    }

    public function accepted()
    {
        return $this->state(function (array $attributes) {
            return [
                'accepted_at' => now(),
            ];
        });
    }

    public function expired()
    {
        return $this->state(function (array $attributes) {
            return [
                'expires_at' => now()->subDays(1),
            ];
        });
    }

    public function revoked()
    {
        return $this->state(function (array $attributes) {
            return [
                'revoked_at' => now(),
            ];
        });
    }
}
