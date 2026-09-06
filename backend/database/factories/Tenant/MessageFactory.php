<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Conversation;
use App\Models\Tenant\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $senderTypes = ['customer', 'agent', 'ai', 'system'];
        $messageTypes = ['text', 'internal_note', 'system_event'];

        $senderType = $this->faker->randomElement($senderTypes);
        $isInternal = $this->faker->boolean(20);
        $messageType = $isInternal ? 'internal_note' : 'text';

        return [
            'conversation_id' => Conversation::factory(),
            'sender_type' => $senderType,
            'sender_id' => $senderType === 'customer' ? 1 : null,
            'message_type' => $messageType,
            'content' => $this->faker->sentence(10),
            'is_internal' => $isInternal,
            'metadata' => [],
        ];
    }

    public function customer()
    {
        return $this->state(function (array $attributes) {
            return [
                'sender_type' => 'customer',
                'is_internal' => false,
            ];
        });
    }

    public function agent()
    {
        return $this->state(function (array $attributes) {
            return [
                'sender_type' => 'agent',
                'is_internal' => false,
            ];
        });
    }

    public function ai()
    {
        return $this->state(function (array $attributes) {
            return [
                'sender_type' => 'ai',
                'sender_id' => null,
                'is_internal' => false,
            ];
        });
    }

    public function system()
    {
        return $this->state(function (array $attributes) {
            return [
                'sender_type' => 'system',
                'sender_id' => null,
                'message_type' => 'system_event',
                'is_internal' => true,
            ];
        });
    }

    public function internal()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_internal' => true,
                'message_type' => 'internal_note',
            ];
        });
    }

}