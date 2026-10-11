<?php

namespace Tests\Feature\Console;

use App\Events\AI\AIResponseGenerated;
use App\Events\Conversation\ConversationCreated;
use App\Events\Message\MessageCreated;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EventAndScheduleReliabilityTest extends TestCase
{
    public function test_registered_event_has_exactly_one_email_verification_listener(): void
    {
        $listeners = app('events')->getListeners(Registered::class);

        $this->assertCount(
            1,
            $listeners,
            'the email verification listener must be registered exactly once'
        );
    }

    public function test_ai_pipeline_listeners_are_registered_exactly_once(): void
    {
        $events = app('events');

        $messageListeners = $events->getListeners(MessageCreated::class);
        $this->assertCount(2, $messageListeners, 'MessageCreated should have the AI + usage listeners');

        $this->assertCount(1, $events->getListeners(ConversationCreated::class));
        $this->assertCount(1, $events->getListeners(AIResponseGenerated::class));
    }

    public function test_storage_sync_is_scheduled_only_once(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertSame(
            1,
            substr_count($output, 'billing:sync-storage'),
            'billing:sync-storage must be scheduled exactly once'
        );
    }
}
