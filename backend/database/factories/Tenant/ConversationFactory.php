<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Conversation;
use App\Models\Tenant\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{

    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['open', 'pending', 'resolved', 'closed'];
        $priorities = ['low', 'normal', 'high', 'urgent'];
        $channels = ['web', 'api'];
        return [
            'customer_id' => Customer::factory(),
            'subject' => $this->faker->sentence(6),
            'channel' => $this->faker->randomElement($channels),
            'status' => $this->faker->randomElement($statuses),
            'priority' => $this->faker->randomElement($priorities),
            'assigned_user_id' => null,
            'last_message_at' => $this->faker->dateTimeThisMonth(),
            'started_at' => $this->faker->dateTimeThisMonth(),
            'resolved_at' => null,
            'closed_at' => null,
            'metadata' => [],
        ];
    }

    public function open()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'open',
            ];
        });
    }

    public function pending()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
            ];
        });
    }

    public function resolved()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'resolved',
                'resolved_at' => now(),
            ];
        });
    }

    public function closed()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'closed',
                'closed_at' => now(),
            ];
        });
    }

    public function urgent()
    {
        return $this->state(function (array $attributes) {
            return [
                'priority' => 'urgent',
            ];
        });
    }

    public function assigned(int $userId)
    {
        return $this->state(function (array $attributes) use ($userId) {
            return [
                'assigned_user_id' => $userId,
            ];
        });
    }

}
