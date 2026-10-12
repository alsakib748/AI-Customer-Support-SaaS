<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Tenant\AIUsage;
use App\Models\Tenant\QuotaReservation;
use App\Services\Billing\BillingLimitService;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\UsageTracker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Atomic quota reservations replace the racy read-then-compare check for the
 * AI paths: the slot is claimed with a single conditional UPDATE before the
 * provider is called.
 */
class QuotaReservationTest extends TestCase
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

        Schema::create('quota_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('feature');
            $table->string('period', 32);
            $table->unsignedBigInteger('reserved_count')->default(0);
            $table->timestamps();

            $table->unique(['feature', 'period']);
        });
    }

    /**
     * Bind a real subscription/plan (with a JSON limit) without touching the
     * central database, then resolve the service under test.
     *
     * @param  int|null  $limit  null = unlimited
     */
    protected function service(?int $limit): BillingLimitService
    {
        // Mock the plan directly: Plan::getLimit()'s JSON fallback splits dotted
        // feature keys into nested paths, and the real path needs a central DB.
        $plan = \Mockery::mock(Plan::class);
        $plan->shouldReceive('getLimit')->andReturn($limit);

        $subscription = new Subscription;
        $subscription->id = 1;
        $subscription->setRelation('plan', $plan);

        $this->mock(SubscriptionService::class, function ($mock) use ($subscription) {
            $mock->shouldReceive('getEntitlingSubscription')->andReturn($subscription);
        });

        $this->mock(UsageTracker::class);

        return app(BillingLimitService::class);
    }

    protected function current(BillingLimitService $service, string $feature): int
    {
        $method = new \ReflectionMethod($service, 'current');
        $method->setAccessible(true);

        return (int) $method->invoke($service, new Tenant, $feature);
    }

    protected function tenant(): Tenant
    {
        $tenant = new Tenant;
        $tenant->id = 'tenant-1';

        return $tenant;
    }

    public function test_reserve_claims_slots_atomically_up_to_the_limit(): void
    {
        $service = $this->service(2);
        $tenant = $this->tenant();

        $this->assertNotNull($service->reserve($tenant, 'ai.requests.monthly', 1));
        $this->assertNotNull($service->reserve($tenant, 'ai.requests.monthly', 1));

        // Third claim exceeds the limit and must be denied even though the two
        // in-flight reservations are invisible to AIUsage.
        $this->assertNull($service->reserve($tenant, 'ai.requests.monthly', 1));

        $this->assertSame(
            2,
            (int) QuotaReservation::query()->where('feature', 'ai.requests.monthly')->value('reserved_count'),
        );
    }

    public function test_reservation_cannot_exceed_remaining_headroom(): void
    {
        $service = $this->service(3);

        AIUsage::create([
            'provider' => 'gemini',
            'model' => 'm',
            'operation' => 'chat',
            'metadata' => ['status' => 'completed'],
        ]);

        // 3 - 1 completed = 2 headroom.
        $this->assertNotNull($service->reserve($this->tenant(), 'ai.requests.monthly', 2));
        $this->assertNull($service->reserve($this->tenant(), 'ai.requests.monthly', 1));
    }

    public function test_release_frees_the_slot_for_the_next_request(): void
    {
        $service = $this->service(1);
        $tenant = $this->tenant();

        $reservation = $service->reserve($tenant, 'ai.requests.monthly', 1);
        $this->assertNotNull($reservation);
        $this->assertNull($service->reserve($tenant, 'ai.requests.monthly', 1));

        $service->release($reservation);

        $this->assertNotNull($service->reserve($tenant, 'ai.requests.monthly', 1));
        $this->assertSame(0, QuotaReservation::query()->where('reserved_count', '<=', 0)->count());
    }

    public function test_in_flight_processing_claims_are_not_double_counted(): void
    {
        $service = $this->service(2);

        AIUsage::create([
            'provider' => 'gemini',
            'model' => 'm',
            'operation' => 'chat',
            'metadata' => ['status' => 'processing'],
        ]);

        $this->assertSame(0, $this->current($service, 'ai.requests.monthly'));

        AIUsage::create([
            'provider' => 'gemini',
            'model' => 'm',
            'operation' => 'chat',
            'metadata' => ['status' => 'completed'],
        ]);

        $this->assertSame(1, $this->current($service, 'ai.requests.monthly'));
    }

    public function test_unlimited_plan_always_reserves(): void
    {
        $service = $this->service(null);

        for ($i = 0; $i < 3; $i++) {
            $this->assertNotNull($service->reserve($this->tenant(), 'ai.requests.monthly', 1));
        }
    }

    public function test_no_subscription_denies_reservation(): void
    {
        $this->mock(SubscriptionService::class, function ($mock) {
            $mock->shouldReceive('getEntitlingSubscription')->andReturn(null);
        });
        $this->mock(UsageTracker::class);

        $service = app(BillingLimitService::class);

        $this->assertNull($service->reserve($this->tenant(), 'ai.requests.monthly', 1));
    }
}
