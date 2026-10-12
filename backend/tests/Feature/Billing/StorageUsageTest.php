<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\BillingLimitService;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\UsageTracker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regression guard for storage usage: it must be measured from the tenant's
 * own database (no tenant_id column exists there) and persisted to the
 * subscription, and BillingLimitService must treat storage_bytes as metered.
 */
class StorageUsageTest extends TestCase
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
            'tenancy.database.central_connection' => 'central',
            'cache.default' => 'array',
        ]);

        DB::purge('tenant');

        Schema::connection('tenant')->create('knowledge_base_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        tenancy()->initialized = false;

        Mockery::close();

        parent::tearDown();
    }

    protected function invokeCalculation(string $tenantId): int
    {
        $service = new UsageTracker;
        $method = new ReflectionMethod($service, 'calculateStorageBytes');
        $method->setAccessible(true);

        return (int) $method->invoke($service, $tenantId);
    }

    public function test_storage_bytes_count_tenant_article_text(): void
    {
        DB::connection('tenant')->table('knowledge_base_articles')->insert([
            ['id' => 1, 'title' => 'Hello', 'excerpt' => 'Short', 'content' => 'Body text', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $expected = strlen('Hello') + strlen('Short') + strlen('Body text');

        $this->assertSame($expected, $this->invokeCalculation('tenant-x'));
    }

    public function test_storage_bytes_ignore_soft_deleted_articles(): void
    {
        DB::connection('tenant')->table('knowledge_base_articles')->insert([
            ['id' => 1, 'title' => 'Gone', 'excerpt' => null, 'content' => 'Trashed', 'deleted_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertSame(0, $this->invokeCalculation('tenant-x'));
    }

    public function test_sync_storage_usage_persists_measured_bytes_to_subscription(): void
    {
        Schema::connection('central')->create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->json('data')->nullable();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('central')->create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable();
            $table->string('tenant_id');
            $table->string('status')->default('active');
            $table->unsignedBigInteger('storage_used')->default(0);
            $table->unsignedBigInteger('storage_limit')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Tenant::query()->create(['id' => 'tenant-sync', 'name' => 'Sync Ltd']);
        $subscription = Subscription::query()->create([
            'tenant_id' => 'tenant-sync',
            'status' => 'active',
            'storage_used' => 0,
        ]);

        DB::connection('tenant')->table('knowledge_base_articles')->insert([
            ['id' => 1, 'title' => 'Doc', 'excerpt' => 'AB', 'content' => 'Content!', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Pretend the tenant connection is already pointed at this tenant.
        tenancy()->initialized = true;

        (new UsageTracker)->syncStorageUsage('tenant-sync');

        $expected = strlen('Doc') + strlen('AB') + strlen('Content!');
        $this->assertSame($expected, (int) $subscription->fresh()->storage_used);
    }

    public function test_billing_limit_treats_storage_bytes_as_metered(): void
    {
        $plan = new Plan(['limits' => ['storage_bytes' => 1000]]);
        $plan->setRelation('planFeatures', collect());

        $subscription = new Subscription(['storage_used' => 900]);
        $subscription->setRelation('plan', $plan);

        $subscriptions = Mockery::mock(SubscriptionService::class);
        $subscriptions->shouldReceive('getEntitlingSubscription')->andReturn($subscription);

        $service = new BillingLimitService($subscriptions, Mockery::mock(UsageTracker::class));

        $tenant = new Tenant;
        $tenant->id = 'tenant-x';

        $denied = $service->check($tenant, 'storage_bytes', 200);
        $this->assertFalse($denied['allowed']);
        $this->assertSame(900, $denied['data']['current']);

        $allowed = $service->check($tenant, 'storage_bytes', 50);
        $this->assertTrue($allowed['allowed']);
    }
}
