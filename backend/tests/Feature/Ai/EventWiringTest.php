<?php

namespace Tests\Feature\Ai;

use App\Events\AI\AIResponseGenerated;
use App\Events\Conversation\ConversationCreated;
use App\Events\KnowledgeBase\ArticleCreated;
use App\Events\Message\MessageCreated;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Tenant;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\KnowledgeBaseArticle;
use App\Models\Tenant\Message;
use App\Services\Billing\BillingLimitService;
use App\Services\Billing\UsageTracker;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * Verifies the explicit event -> listener wiring in EventServiceProvider for
 * the listeners whose handle() type-hints a generic `object` (and therefore
 * cannot be auto-discovered).
 */
class EventWiringTest extends TestCase
{
    protected function bindCurrentTenant(string $id): void
    {
        $tenant = new Tenant;
        $tenant->id = $id;

        $this->app->instance('current_tenant', $tenant);
    }

    public function test_message_created_tracks_usage_for_current_tenant(): void
    {
        $this->bindCurrentTenant('tenant-abc');

        $this->mock(UsageTracker::class, function ($mock) {
            $mock->shouldReceive('track')
                ->once()
                ->with('tenant-abc', 'messages', 1);
        });

        MessageCreated::dispatch(new Message(['content' => 'hello']));

        $this->addToAssertionCount(1);
    }

    public function test_conversation_created_tracks_usage_for_current_tenant(): void
    {
        $this->bindCurrentTenant('tenant-conv');

        $this->mock(UsageTracker::class, function ($mock) {
            $mock->shouldReceive('track')
                ->once()
                ->with('tenant-conv', 'conversations', 1);
        });

        ConversationCreated::dispatch();

        $this->addToAssertionCount(1);
    }

    public function test_article_created_tracks_usage_for_current_tenant(): void
    {
        $this->bindCurrentTenant('tenant-kb');

        $this->mock(UsageTracker::class, function ($mock) {
            $mock->shouldReceive('track')
                ->once()
                ->with('tenant-kb', 'kb_articles', 1);
        });

        ArticleCreated::dispatch(new KnowledgeBaseArticle(['title' => 'Doc']));

        $this->addToAssertionCount(1);
    }

    public function test_domain_events_are_ignored_when_no_tenant_is_resolvable(): void
    {
        $this->app->instance('current_tenant', null);

        $this->mock(UsageTracker::class, function ($mock) {
            $mock->shouldNotReceive('track');
        });

        MessageCreated::dispatch(new Message(['content' => 'hello']));

        $this->addToAssertionCount(1);
    }

    public function test_ai_response_generated_triggers_billing_enforcement(): void
    {
        $this->bindCurrentTenant('tenant-billing');

        $this->mock(BillingLimitService::class, function ($mock) {
            $mock->shouldReceive('enforce')
                ->once()
                ->with(Mockery::on(fn ($tenant) => $tenant->id === 'tenant-billing'), 'ai.requests.monthly', 1);
        });

        AIResponseGenerated::dispatch(new Conversation, new Message, [
            'usage' => ['total_tokens' => 0],
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_ai_response_generated_enforces_token_limit_when_usage_present(): void
    {
        $this->bindCurrentTenant('tenant-billing');

        $this->mock(BillingLimitService::class, function ($mock) {
            $mock->shouldReceive('enforce')->once()->with(Mockery::type('object'), 'ai.requests.monthly', 1);
            $mock->shouldReceive('enforce')->once()->with(Mockery::type('object'), 'ai.tokens.monthly', 1234);
        });

        AIResponseGenerated::dispatch(new Conversation, new Message, [
            'usage' => ['total_tokens' => 1234],
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_billing_breach_after_generation_is_logged_not_thrown(): void
    {
        $this->bindCurrentTenant('tenant-billing');

        $this->mock(BillingLimitService::class, function ($mock) {
            $mock->shouldReceive('enforce')
                ->once()
                ->andThrow(new PlanLimitExceededException('limit', 'PLAN_LIMIT_REACHED', []));
        });

        Log::shouldReceive('warning')->atLeast()->once();

        // Must NOT throw: the generation already happened, so failing here would
        // abort a successful job.
        AIResponseGenerated::dispatch(new Conversation, new Message, []);

        $this->addToAssertionCount(1);
    }
}
