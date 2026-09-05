<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Conversation;
use App\Models\Tenant\Customer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ConversationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all customers
        $customers = Customer::all();

        if ($customers->isEmpty()) {
            $this->command->warn('No customers found. Please run CustomerSeeder first.');
            return;
        }

        // Create conversations for each customer
        $statuses = ['open', 'pending', 'resolved', 'closed'];
        $priorities = ['low', 'normal', 'high', 'urgent'];
        $channels = ['web', 'api'];

        foreach ($customers as $customer) {
            // Create 2-5 conversations per customer
            $count = rand(2, 5);

            for ($i = 0; $i < $count; $i++) {
                $status = $this->faker->randomElement($statuses);
                $priority = $this->faker->randomElement($priorities);

                $conversation = Conversation::create([
                    'customer_id' => $customer->id,
                    'subject' => $this->faker->sentence(6),
                    'channel' => $this->faker->randomElement($channels),
                    'status' => $status,
                    'priority' => $priority,
                    'assigned_user_id' => null,
                    'last_message_at' => $this->faker->dateTimeThisMonth(),
                    'started_at' => $this->faker->dateTimeThisMonth(),
                    'resolved_at' => $status === 'resolved' ? now() : null,
                    'closed_at' => $status === 'closed' ? now() : null,
                    'metadata' => [],
                ]);

                // Add some metadata
                $conversation->update([
                    'metadata' => [
                        'source' => $this->faker->randomElement(['chat', 'email', 'phone']),
                        'browser' => $this->faker->randomElement(['Chrome', 'Firefox', 'Safari']),
                        'device' => $this->faker->randomElement(['desktop', 'mobile', 'tablet']),
                    ],
                ]);
            }
        }

        $this->command->info('Conversations seeded successfully!');
    }

    protected $faker;

    public function __construct()
    {
        $this->faker = \Faker\Factory::create();
    }

}