<?php

namespace Tests\Feature\Ai;

use App\Ai\Services\AIService;
use App\Exceptions\PlanLimitExceededException;
use App\Jobs\AI\ProcessCustomerMessage;
use App\Models\Tenant\Message;
use App\Services\Billing\BillingLimitService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Stancl\Tenancy\Facades\Tenancy;
use Tests\TestCase;

/**
 * Verifies the hard pre-flight billing gate in ProcessCustomerMessage: an
 * over-quota tenant must never reach the AI provider.
 */
class ProcessCustomerMessageEnforcementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tenancy.database.central_connection' => 'central',
            'database.connections.tenant' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        DB::purge('tenant');
        DB::purge('central');

        // Central tenants table (Tenant model resolves on the central connection).
        Schema::connection('central')->create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::connection('central')->table('tenants')->insert([
            'id' => 'tenant-1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function fakeTenancy(): void
    {
        Tenancy::shouldReceive('initialize')->once();
        Tenancy::shouldReceive('end')->once();
    }

    public function test_over_quota_tenant_never_reaches_the_ai_provider(): void
    {
        $this->fakeTenancy();

        $this->mock(BillingLimitService::class, function ($mock) {
            $mock->shouldReceive('enforce')
                ->once()
                ->with(Mockery::on(fn ($tenant) => $tenant->id === 'tenant-1'), 'ai.requests.monthly', 1)
                ->andThrow(new PlanLimitExceededException('limit reached', 'PLAN_LIMIT_REACHED', []));
        });

        $this->mock(AIService::class, function ($mock) {
            $mock->shouldNotReceive('processMessage');
        });

        // Must return silently, not throw, and must not call the AI service.
        (new ProcessCustomerMessage(99, 'tenant-1'))->handle();

        $this->addToAssertionCount(1);
    }

    public function test_within_quota_tenant_is_processed(): void
    {
        $this->fakeTenancy();

        Schema::connection('tenant')->create('messages', function (Blueprint $table) {
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

        $message = Message::create([
            'conversation_id' => 1,
            'sender_type' => 'customer',
            'message_type' => 'text',
            'content' => 'help',
            'is_internal' => false,
        ]);

        $this->mock(BillingLimitService::class, function ($mock) {
            $mock->shouldReceive('enforce')->once()->andReturn(null);
        });

        $this->mock(AIService::class, function ($mock) use ($message) {
            $mock->shouldReceive('processMessage')
                ->once()
                ->with(Mockery::on(fn ($m) => $m->id === $message->id))
                ->andReturn(['success' => true, 'message' => 'ok']);
        });

        (new ProcessCustomerMessage($message->id, 'tenant-1'))->handle();

        $this->addToAssertionCount(1);
    }
}
