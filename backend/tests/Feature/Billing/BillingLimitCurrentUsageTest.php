<?php

namespace Tests\Feature\Billing;

use App\Models\Tenant;
use App\Models\Tenant\AIUsage;
use App\Services\Billing\BillingLimitService;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\UsageTracker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regression guard: BillingLimitService::current() must count tenant-scoped
 * resources through the tenant connection without referencing a column that
 * does not exist (tenant tables are isolated by connection and have no
 * tenant_id, and ai_usage stores total_tokens, not tokens_used).
 */
class BillingLimitCurrentUsageTest extends TestCase
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
            ],
        ]);

        DB::purge('tenant');

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
        });
    }

    protected function service(): BillingLimitService
    {
        $this->mock(SubscriptionService::class);
        $this->mock(UsageTracker::class);

        return app(BillingLimitService::class);
    }

    protected function invokeCurrent(BillingLimitService $service, string $feature): int
    {
        $method = new ReflectionMethod($service, 'current');
        $method->setAccessible(true);

        return (int) $method->invoke($service, new Tenant, $feature);
    }

    public function test_ai_request_usage_counts_rows_for_the_current_month(): void
    {
        AIUsage::create(['provider' => 'gemini', 'model' => 'm', 'operation' => 'chat']);
        AIUsage::create(['provider' => 'gemini', 'model' => 'm', 'operation' => 'chat']);

        // Last month's rows must not count.
        $old = AIUsage::create(['provider' => 'gemini', 'model' => 'm', 'operation' => 'chat']);
        $old->created_at = now()->subMonth();
        $old->updated_at = now()->subMonth();
        $old->save();

        $this->assertSame(2, $this->invokeCurrent($this->service(), 'ai.requests.monthly'));
    }

    public function test_ai_token_usage_sums_total_tokens_column(): void
    {
        AIUsage::create(['provider' => 'gemini', 'model' => 'm', 'operation' => 'chat', 'total_tokens' => 100]);
        AIUsage::create(['provider' => 'gemini', 'model' => 'm', 'operation' => 'chat', 'total_tokens' => 250]);

        $this->assertSame(350, $this->invokeCurrent($this->service(), 'ai.tokens.monthly'));
    }
}
