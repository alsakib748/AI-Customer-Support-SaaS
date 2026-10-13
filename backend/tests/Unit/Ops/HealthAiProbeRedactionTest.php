<?php

namespace Tests\Unit\Ops;

use App\Health\Checks\AiProviderHealthCheck;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 6 WS4 regression: health metadata must never contain an AI provider
 * API key, and a URL carrying a credential must be redacted before storage.
 */
class HealthAiProbeRedactionTest extends TestCase
{
    public function test_redact_url_masks_credential_query_parameters(): void
    {
        $check = new class extends AiProviderHealthCheck
        {
            public function redact(string $url): string
            {
                return $this->redactUrl($url);
            }
        };

        $this->assertSame(
            'https://provider.test/v1/models?key=REDACTED',
            $check->redact('https://provider.test/v1/models?key=SUPER-SECRET'),
        );

        $this->assertSame(
            'https://provider.test/v1/models?api_key=REDACTED&page=1',
            $check->redact('https://provider.test/v1/models?api_key=SUPER-SECRET&page=1'),
        );

        $this->assertSame(
            'https://provider.test/v1/models?access_token=REDACTED',
            $check->redact('https://provider.test/v1/models?access_token=SUPER-SECRET'),
        );

        // A benign URL is returned untouched.
        $this->assertSame(
            'https://provider.test/v1/models?page=2',
            $check->redact('https://provider.test/v1/models?page=2'),
        );
    }

    public function test_health_metadata_never_contains_the_gemini_key(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        config([
            'ai.default' => 'gemini',
            'ai.providers.gemini.key' => 'GEMINI-MUST-NOT-LEAK',
            'ai.providers.gemini.url' => 'https://generativelanguage.test/v1beta',
            'health.checks.ai.provider.probe' => true,
        ]);

        $result = (new AiProviderHealthCheck)->run();

        $this->assertStringNotContainsString('GEMINI-MUST-NOT-LEAK', json_encode($result->toArray()));
    }

    public function test_health_metadata_never_contains_the_openai_key(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        config([
            'ai.default' => 'openai',
            'ai.providers.openai.key' => 'OPENAI-MUST-NOT-LEAK',
            'ai.providers.openai.url' => 'https://api.openai.test/v1',
            'health.checks.ai.provider.probe' => true,
        ]);

        $result = (new AiProviderHealthCheck)->run();

        $this->assertStringNotContainsString('OPENAI-MUST-NOT-LEAK', json_encode($result->toArray()));
        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'OPENAI-MUST-NOT-LEAK'));
    }
}
