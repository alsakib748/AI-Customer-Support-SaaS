<?php

namespace Tests\Feature\Ai;

use App\Ai\Services\AIStreamingService;
use App\Ai\Services\GeminiService;
use App\Events\AI\AIResponseGenerated;
use App\Models\Tenant\AIConfiguration;
use App\Models\Tenant\AILog;
use App\Models\Tenant\AIUsage;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Message;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The streaming path must account for usage exactly like the queued path so
 * that streamed replies grow the tenant's metered quota.
 */
class AIStreamingUsageTest extends TestCase
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

    protected function seedScenario(): array
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

    protected function consume($response): string
    {
        // Two nested buffers: ob_flush() inside the service moves the active
        // (inner) buffer into the outer one, so the full stream is captured.
        ob_start();
        ob_start();

        $response->sendContent();

        $inner = ob_get_clean();
        $outer = ob_get_clean();

        return $outer.$inner;
    }

    public function test_streamed_response_records_estimated_usage_and_audit_log(): void
    {
        [$conversation, $message] = $this->seedScenario();

        Event::fake([AIResponseGenerated::class]);

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('streamContent')
                ->once()
                ->andReturnUsing(function ($prompt, $callback) {
                    $callback('Sure, ');
                    $callback('I can help.');
                });
            $mock->shouldNotReceive('generateContent');
        });

        $service = app(AIStreamingService::class);
        $body = $this->consume($service->streamResponse($conversation, $message));

        $this->assertStringContainsString('Sure, ', $body);
        $this->assertStringContainsString('I can help.', $body);
        $this->assertStringContainsString('"type":"complete"', $body);

        $usage = AIUsage::where('message_id', $message->id)->where('operation', 'chat_stream')->first();
        $this->assertNotNull($usage);
        $this->assertSame('completed', $usage->metadata['status']);
        $this->assertTrue($usage->metadata['tokens_estimated']);
        $this->assertSame(2, $usage->metadata['streamed_chunks']);
        $this->assertGreaterThan(0, $usage->total_tokens);
        $this->assertGreaterThan(0, (float) $usage->estimated_cost);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_type' => 'ai',
        ]);

        $log = AILog::where('message_id', $message->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);

        Event::assertDispatched(AIResponseGenerated::class);
    }

    public function test_streaming_records_usage_even_when_provider_fails(): void
    {
        [$conversation, $message] = $this->seedScenario();

        Event::fake([AIResponseGenerated::class]);

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('streamContent')->once()->andThrow(new \RuntimeException('boom'));
            $mock->shouldNotReceive('generateContent');
        });

        $service = app(AIStreamingService::class);
        $body = $this->consume($service->streamResponse($conversation, $message));

        $this->assertStringContainsString('error', $body);
        $this->assertStringNotContainsString('boom', $body);

        $usage = AIUsage::where('message_id', $message->id)->where('operation', 'chat_stream')->first();
        $this->assertNotNull($usage);
        $this->assertSame('failed', $usage->metadata['status']);

        $log = AILog::where('message_id', $message->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('failed', $log->status);
    }
}
