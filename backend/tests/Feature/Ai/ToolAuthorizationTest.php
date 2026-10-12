<?php

namespace Tests\Feature\Ai;

use App\Ai\Tools\EscalateConversationTool;
use App\Ai\Tools\GetConversationTool;
use App\Ai\Tools\GetCustomerTool;
use App\Ai\Tools\SearchKnowledgeBaseTool;
use App\Models\Tenant;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\Customer;
use App\Models\Tenant\KnowledgeBaseArticle;
use App\Services\Conversation\ConversationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * Verifies that AI tools only run inside a tenant context, require the
 * server-set conversation context, and never act on model-supplied ids that
 * point outside the conversation being served.
 */
class ToolAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'tenant',
            'database.connections.tenant' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        DB::purge('tenant');

        $this->createTenantSchema();
    }

    protected function createTenantSchema(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('subject')->nullable();
            $table->string('channel')->nullable();
            $table->string('status')->default('open');
            $table->string('priority')->default('normal');
            $table->boolean('escalated')->default(false);
            $table->string('escalated_reason')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('sender_type')->default('customer');
            $table->string('message_type')->default('text');
            $table->text('content')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('knowledge_base_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_base_articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('status')->default('draft');
            $table->string('visibility')->default('ai');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function bindTenant(string $id = 'tenant-a'): Tenant
    {
        $tenant = new Tenant;
        $tenant->id = $id;
        $this->app->instance('current_tenant', $tenant);

        return $tenant;
    }

    protected function customer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'status' => 'active',
        ], $attributes));
    }

    protected function conversation(Customer $customer, array $attributes = []): Conversation
    {
        return Conversation::create(array_merge([
            'customer_id' => $customer->id,
            'subject' => 'Cannot log in',
            'channel' => 'web',
            'status' => 'open',
            'priority' => 'normal',
        ], $attributes));
    }

    public function test_context_tool_without_a_tenant_is_unauthorized(): void
    {
        $customer = $this->customer();
        $conversation = $this->conversation($customer);

        $tool = new GetConversationTool;
        $tool->setConversation($conversation);

        $result = $tool->execute(['conversation_id' => $conversation->id]);

        $this->assertFalse($result['success']);
    }

    public function test_context_tool_without_a_conversation_is_unauthorized(): void
    {
        $this->bindTenant();

        $result = (new GetConversationTool)->execute(['conversation_id' => 1]);

        $this->assertFalse($result['success']);
    }

    public function test_get_conversation_rejects_a_different_conversation_id(): void
    {
        $this->bindTenant();

        $mine = $this->conversation($this->customer(['email' => 'mine@example.com']));
        $other = $this->conversation(
            $this->customer(['email' => 'other@example.com']),
            ['subject' => 'Someone else\'s topic']
        );

        $tool = new GetConversationTool;
        $tool->setConversation($mine);

        $result = $tool->execute(['conversation_id' => $other->id]);

        $this->assertFalse($result['success']);
        $this->assertNull($result['conversation']);
    }

    public function test_get_conversation_returns_the_served_conversation(): void
    {
        $this->bindTenant();

        $conversation = $this->conversation($this->customer());

        $tool = new GetConversationTool;
        $tool->setConversation($conversation);

        $result = $tool->execute(['conversation_id' => $conversation->id]);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertSame($conversation->id, $result['conversation']['id']);
    }

    public function test_get_customer_rejects_a_mismatched_customer(): void
    {
        $this->bindTenant();

        $mine = $this->customer();
        $conversation = $this->conversation($mine);
        $other = $this->customer(['email' => 'other@example.com']);

        $tool = new GetCustomerTool;
        $tool->setConversation($conversation);

        $byId = $tool->execute(['customer_id' => $other->id]);
        $byEmail = $tool->execute(['email' => $other->email]);

        $this->assertFalse($byId['success']);
        $this->assertFalse($byEmail['success']);
    }

    public function test_get_customer_returns_the_conversations_customer(): void
    {
        $this->bindTenant();

        $customer = $this->customer();
        $conversation = $this->conversation($customer);

        $tool = new GetCustomerTool;
        $tool->setConversation($conversation);

        $result = $tool->execute(['email' => strtoupper($customer->email)]);
        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertSame($customer->id, $result['customer']['id']);
    }

    public function test_escalate_rejects_a_different_conversation_id(): void
    {
        $this->bindTenant();

        $service = Mockery::mock(ConversationService::class);

        $mine = $this->conversation($this->customer(['email' => 'mine@example.com']));
        $other = $this->conversation(
            $this->customer(['email' => 'other@example.com']),
            ['subject' => 'Other']
        );

        $tool = new EscalateConversationTool($service);
        $tool->setConversation($mine);

        $result = $tool->execute([
            'conversation_id' => $other->id,
            'reason' => 'Needs a human',
        ]);

        $this->assertFalse($result['success']);
        $this->assertFalse((bool) $other->fresh()->escalated);
    }

    public function test_escalate_updates_only_the_served_conversation(): void
    {
        $this->bindTenant();

        $service = Mockery::mock(ConversationService::class);

        $mine = $this->conversation($this->customer());

        $tool = new EscalateConversationTool($service);
        $tool->setConversation($mine);

        $result = $tool->execute(['reason' => 'Needs a human']);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertTrue((bool) $mine->fresh()->escalated);
    }

    public function test_search_knowledge_base_matches_case_insensitively(): void
    {
        $this->bindTenant();

        KnowledgeBaseArticle::create([
            'title' => 'Refund Policy',
            'slug' => 'refund-policy',
            'content' => 'Refunds are processed within five business days.',
            'status' => 'published',
            'visibility' => 'ai',
            'published_at' => now(),
        ]);

        $result = (new SearchKnowledgeBaseTool)->execute(['query' => 'refunds']);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['count']);
    }

    public function test_search_knowledge_base_without_a_tenant_is_unauthorized(): void
    {
        $result = (new SearchKnowledgeBaseTool)->execute(['query' => 'refunds']);

        $this->assertFalse($result['success']);
    }
}
