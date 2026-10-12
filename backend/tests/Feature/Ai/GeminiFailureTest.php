<?php

namespace Tests\Feature\Ai;

use App\Ai\Services\GeminiService;
use App\Models\Tenant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Provider failures must never leak internal error details (response bodies,
 * exception messages) back to callers, and the daily request quota must be
 * scoped per tenant.
 */
class GeminiFailureTest extends TestCase
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
            'ai.gemini.max_retries' => 1,
            'ai.providers.gemini.key' => 'test-key',
            'cache.default' => 'array',
        ]);

        DB::purge('tenant');

        Schema::create('ai_configurations', function ($table) {
            $table->id();
            $table->string('model')->nullable();
        });
    }

    public function test_http_error_body_is_not_leaked_to_the_caller(): void
    {
        Http::fake([
            '*' => Http::response('secret-provider-detail-xyz', 500),
        ]);

        $result = app(GeminiService::class)->generateContent('hello');

        $this->assertFalse($result['success']);
        $this->assertStringNotContainsString('secret-provider-detail-xyz', $result['error']);
        $this->assertSame('The AI provider returned an error.', $result['error']);
    }

    public function test_connection_exception_message_is_not_leaked_to_the_caller(): void
    {
        Http::fake(function () {
            throw new ConnectionException('secret-boom');
        });

        $result = app(GeminiService::class)->generateContent('hello');

        $this->assertFalse($result['success']);
        $this->assertStringNotContainsString('secret-boom', $result['error']);
    }

    public function test_daily_quota_key_is_scoped_per_tenant(): void
    {
        $service = app(GeminiService::class);
        $method = new \ReflectionMethod($service, 'quotaCacheKey');
        $method->setAccessible(true);

        // Bind a fake current tenant to exercise the fallback resolution.
        $tenantA = new Tenant;
        $tenantA->id = 'tenant-a';
        $this->app->instance('current_tenant', $tenantA);
        $keyA = $method->invoke($service);

        $tenantB = new Tenant;
        $tenantB->id = 'tenant-b';
        $this->app->instance('current_tenant', $tenantB);
        $keyB = $method->invoke($service);

        $this->assertStringContainsString('tenant-a', $keyA);
        $this->assertStringContainsString('tenant-b', $keyB);
        $this->assertNotSame($keyA, $keyB);
    }
}
