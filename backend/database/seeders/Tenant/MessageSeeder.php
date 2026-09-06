<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Conversation;
use App\Models\Tenant\Message;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $conversations = Conversation::all();

        if ($conversations->isEmpty()) {
            $this->command->warn('No conversations found. Please run ConversationSeeder first.');
            return;
        }

        foreach ($conversations as $conversation) {
            // Create 3-8 messages per conversation
            $count = rand(3, 8);
            $customer = $conversation->customer;

            for ($i = 0; $i < $count; $i++) {
                $senderType = $i % 2 === 0 ? 'customer' : 'agent';
                $content = $this->generateMessage($senderType, $i);

                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_type' => $senderType,
                    'sender_id' => $senderType === 'customer' ? $customer->id : null,
                    'message_type' => 'text',
                    'content' => $content,
                    'is_internal' => false,
                    'created_at' => now()->addMinutes($i),
                    'metadata' => [],
                ]);
            }

            // Update conversation last_message_at
            $conversation->update([
                'last_message_at' => now(),
            ]);
        }

        // Create some internal notes
        foreach ($conversations->random(min(3, $conversations->count())) as $conversation) {
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'agent',
                'sender_id' => null,
                'message_type' => 'internal_note',
                'content' => 'Internal note: ' . $this->faker->sentence(6),
                'is_internal' => true,
                'created_at' => now()->addMinutes(rand(5, 10)),
                'metadata' => [],
            ]);
        }

        // Create some system events
        foreach ($conversations->random(min(2, $conversations->count())) as $conversation) {
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'system',
                'sender_id' => null,
                'message_type' => 'system_event',
                'content' => 'System: Conversation was ' . $this->faker->randomElement(['created', 'assigned', 'resolved']),
                'is_internal' => true,
                'created_at' => now()->addMinutes(rand(2, 5)),
                'metadata' => [],
            ]);
        }

        $this->command->info('Messages seeded successfully!');
    }

    protected function generateMessage($senderType, $index)
    {
        $customerMessages = [
            'Hello, I need help with my order.',
            'I cannot log into my account.',
            'My payment was declined.',
            'When will my order arrive?',
            'I want to request a refund.',
            'The product is defective.',
            'How do I reset my password?',
            'Can I change my shipping address?',
            'I have a billing question.',
            'My subscription was cancelled.',
        ];

        $agentMessages = [
            'Hello, I\'ll be happy to help you.',
            'Let me check that for you.',
            'I understand your concern.',
            'Thank you for providing that information.',
            'I\'ve found the issue.',
            'Let me escalate this to our team.',
            'I can assist you with that.',
            'Let me look into your account.',
            'I\'ve resolved the issue.',
            'Is there anything else I can help with?',
        ];

        if ($senderType === 'customer') {
            return $this->faker->randomElement($customerMessages);
        }

        return $this->faker->randomElement($agentMessages);
    }

    protected $faker;

    public function __construct()
    {
        // parent::__construct();
        $this->faker = \Faker\Factory::create();
    }
}