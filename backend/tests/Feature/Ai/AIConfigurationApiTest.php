<?php

namespace Tests\Feature\Ai;

use App\Ai\Services\GeminiService;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithSupportDomain;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

class AIConfigurationApiTest extends TestCase
{
    use InteractsWithCentralSchema;
    use InteractsWithSupportDomain;
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCentralSchema();
        $this->useIsolatedTenantDatabase();
        $this->seedRbac();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantContext();
        parent::tearDown();
    }

    public function test_show_returns_a_default_configuration_when_none_is_stored(): void
    {
        $this->bootSupportWorkspace();

        $this->getJson('/api/v1/ai/configuration')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.provider', 'gemini');
    }

    public function test_updating_ai_configuration_persists_the_changes(): void
    {
        $this->bootSupportWorkspace();

        $this->putJson('/api/v1/ai/configuration', [
            'enabled' => false,
            'auto_reply_enabled' => false,
            'temperature' => 0.25,
            'max_tokens' => 1500,
        ])->assertOk();

        $row = DB::connection('tenant')->table('ai_configurations')->first();
        $this->assertSame(0, (int) $row->enabled);
        $this->assertSame(0, (int) $row->auto_reply_enabled);
        $this->assertEquals(0.25, (float) $row->temperature);
        $this->assertSame(1500, (int) $row->max_tokens);

        $this->getJson('/api/v1/ai/configuration')
            ->assertOk()
            ->assertJsonPath('data.max_tokens', 1500);
    }

    public function test_an_unsupported_provider_is_rejected(): void
    {
        $this->bootSupportWorkspace();

        $this->putJson('/api/v1/ai/configuration', ['provider' => 'not-a-provider'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['provider']]);
    }

    public function test_a_model_that_does_not_match_the_provider_is_rejected(): void
    {
        $this->bootSupportWorkspace();

        $this->putJson('/api/v1/ai/configuration', [
            'provider' => 'gemini',
            'model' => 'gpt-4o',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['model']]);

        $this->assertSame(0, DB::connection('tenant')->table('ai_configurations')->count());
    }

    public function test_testing_the_configuration_returns_the_provider_response(): void
    {
        $this->bootSupportWorkspace();

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('generateContent')
                ->once()
                ->andReturn(['success' => true, 'content' => 'Hello from the model']);
        });

        $this->postJson('/api/v1/ai/test', ['message' => 'Ping'])
            ->assertOk()
            ->assertJsonPath('data.response', 'Hello from the model')
            ->assertJsonPath('data.provider', 'gemini');
    }

    public function test_a_failed_provider_test_returns_502_without_leaking_details(): void
    {
        $this->bootSupportWorkspace();

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('generateContent')
                ->once()
                ->andReturn(['success' => false, 'error' => 'raw upstream secret']);
        });

        $response = $this->postJson('/api/v1/ai/test', ['message' => 'Ping']);
        $response->assertStatus(502);
        $this->assertStringNotContainsString('raw upstream secret', $response->getContent());
    }

    public function test_testing_the_configuration_requires_a_message(): void
    {
        $this->bootSupportWorkspace();

        $this->postJson('/api/v1/ai/test', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['message']]);
    }

    public function test_ai_configuration_is_isolated_per_tenant(): void
    {
        $this->bootSupportWorkspace();
        $this->putJson('/api/v1/ai/configuration', ['provider' => 'gemini', 'enabled' => false])->assertOk();
        $this->assertSame(1, DB::connection('tenant')->table('ai_configurations')->count());

        $tenantB = $this->newTenant();
        $ownerB = $this->newTenantUser($tenantB, 'owner');
        $this->newSubscription($tenantB);
        $this->establishTenantContext($tenantB, $ownerB);

        $this->assertSame(0, DB::connection('tenant')->table('ai_configurations')->count());
        $this->getJson('/api/v1/ai/configuration')
            ->assertOk()
            ->assertJsonPath('data.enabled', true);
    }
}
