<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company();
        $slug = Str::slug($name);

        return [
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'subdomain' => $slug,
            'domain' => $slug . '.example.com',
            'industry' => $this->faker->jobTitle(),
            'timezone' => 'UTC',
            'default_language' => 'en',
            'support_email' => $this->faker->companyEmail(),
            'support_phone' => $this->faker->phoneNumber(),
            'status' => 'active',
            'settings' => [],
            'metadata' => [],
            'data' => [],
        ];
    }
}
