<?php

namespace Tests\Feature\Ai;

use App\Ai\Services\AIService;
use App\Ai\Services\GeminiService;
use App\Events\AI\AIResponseGenerated;
use App\Models\Tenant\AIConfiguration;
use App\Models\Tenant\AIUsage;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Message;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises the AI pipeline against an isolated in-memory tenant database.
 *
 * Both the default connection and the dedicated `tenant` connection point at
 * the same SQLite database so that models with and without an explicit
 * $connection (e.g. Customer) resolve to the same tables.
 */
class AiMessageProcessingTest extends TestCase
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
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('status')->default('active');
            $table->integer('total_conversations')->default(0);
            $table->integer('total_tickets')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('subject')->nullable();
            $table->string('channel')->default('web');
            $table->string('status')->default('open');
            $table->string('priority')->default('normal');
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('sender_type');
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('message_type')->default('text');
            $table->text('content')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ai_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('gemini');
            $table->string('model')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('auto_reply_enabled')->default(true);
            $table->boolean('auto_escalation_enabled')->default(true);
            $table->boolean('streaming_enabled')->default(true);
            $table->boolean('knowledge_base_enabled')->default(false);
            $table->decimal('temperature', 3, 2)->default(0.7);
            $table->integer('max_tokens')->default(2000);
            $table->text('system_prompt')->nullable();
            $table->text('custom_instructions')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('provider');
            $table->string('model');
            $table->string('operation')->default('chat');
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->decimal('estimated_cost', 10, 6)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'operation'], 'ai_usage_message_operation_unique');
        });

        Schema::create('ai_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('agent');
            $table->string('provider');
            $table->string('model');
            $table->string('request_id')->nullable();
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->integer('duration_ms')->nullable();
            $table->string('status')->default('success');
            $table->string('error_type')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    protected function seedConfiguration(): void
    {
        AIConfiguration::create([
            'provider' => 'gemini',
            'model' => 'gemini-1.5-flash',
            'enabled' => true,
            'auto_reply_enabled' => true,
            'auto_escalation_enabled' => true,
            'streaming_enabled' => true,
            'knowledge_base_enabled' => false,
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'system_prompt' => 'You are a helpful support assistant.',
        ]);
    }

    protected function seedConversation(): array
    {
        $customer = Customer::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'status' => 'active',
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'subject' => 'Need help',
            'channel' => 'web',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'customer',
            'message_type' => 'text',
            'content' => 'Hello, I need help with my order.',
            'is_internal' => false,
        ]);

        return [$conversation, $message];
    }

    protected function mockGeminiSuccess(int $times = 1): void
    {
        $this->mock(GeminiService::class, function ($mock) use ($times) {
            $mock->shouldReceive('generateContent')
                ->times($times)
                ->andReturn([
                    'success' => true,
                    'content' => 'Sure, I can help with that.',
                    'finish_reason' => 'STOP',
                    'usage' => [
                        'prompt_tokens' => 100,
                        'completion_tokens' => 20,
                        'total_tokens' => 120,
                    ],
                ]);
        });
    }

    public function test_process_message_creates_reply_and_records_usage_once(): void
    {
        $this->seedConfiguration();
        [$conversation, $message] = $this->seedConversation();

        Event::fake([AIResponseGenerated::class]);
        $this->mockGeminiSuccess(1);

        $result = app(AIService::class)->processMessage($message);

        $this->assertTrue($result['success']);

        $this->assertSame(
            1,
            Message::where('conversation_id', $conversation->id)->where('sender_type', 'ai')->count(),
        );

        $usage = AIUsage::where('message_id', $message->id)->where('operation', 'chat')->get();
        $this->assertCount(1, $usage);
        $this->assertSame(120, (int) $usage->first()->total_tokens);

        Event::assertDispatched(AIResponseGenerated::class);
    }

    public function test_duplicate_processing_is_rejected_by_the_unique_claim(): void
    {
        $this->seedConfiguration();
        [$conversation, $message] = $this->seedConversation();

        Event::fake([AIResponseGenerated::class]);
        $this->mockGeminiSuccess(1);

        $service = app(AIService::class);
        $first = $service->processMessage($message);
        $this->assertTrue($first['success']);

        // Simulate a concurrent worker that never saw the AI reply: remove the
        // reply message so the hasAIResponse() guard no longer trips, leaving
        // the ai_usage claim as the only protection.
        Message::where('conversation_id', $conversation->id)->where('sender_type', 'ai')->delete();

        $second = $service->processMessage($message);

        $this->assertFalse($second['success']);
        $this->assertTrue($second['skipped'] ?? false);

        // Gemini is mocked to accept exactly ONE call, so reaching it again
        // would fail the test; the usage claim must remain single.
        $this->assertSame(
            1,
            AIUsage::where('message_id', $message->id)->where('operation', 'chat')->count(),
        );
    }

    public function test_unique_claim_constraint_is_enforced_at_the_database_level(): void
    {
        AIUsage::create([
            'message_id' => 42,
            'provider' => 'gemini',
            'model' => 'gemini-1.5-flash',
            'operation' => 'chat',
        ]);

        $this->expectException(QueryException::class);

        AIUsage::create([
            'message_id' => 42,
            'provider' => 'gemini',
            'model' => 'gemini-1.5-flash',
            'operation' => 'chat',
        ]);
    }

    public function test_claim_is_released_when_provider_returns_a_failure(): void
    {
        $this->seedConfiguration();
        [$conversation, $message] = $this->seedConversation();

        Event::fake([AIResponseGenerated::class]);

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('generateContent')->once()->andReturn([
                'success' => false,
                'error' => 'provider exploded',
            ]);
        });

        $result = app(AIService::class)->processMessage($message);

        $this->assertFalse($result['success']);

        // Claim must be released so a retry can attempt processing again.
        $this->assertSame(0, AIUsage::where('message_id', $message->id)->count());
        $this->assertSame(0, Message::where('conversation_id', $conversation->id)->where('sender_type', 'ai')->count());
    }

    public function test_processing_is_skipped_when_ai_is_disabled(): void
    {
        AIConfiguration::create([
            'provider' => 'gemini',
            'model' => 'gemini-1.5-flash',
            'enabled' => false,
            'auto_reply_enabled' => false,
        ]);

        [, $message] = $this->seedConversation();

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldNotReceive('generateContent');
        });

        $result = app(AIService::class)->processMessage($message);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['skipped'] ?? false);
    }
}
