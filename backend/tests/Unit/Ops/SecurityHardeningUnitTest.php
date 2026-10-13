<?php

namespace Tests\Unit\Ops;

use App\Health\Checks\AiProviderHealthCheck;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\WidgetRateLimit;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Pure/unit-level regression coverage for the Phase 6 WS3 security hardening
 * changes that do not require a database or the HTTP stack.
 */
class SecurityHardeningUnitTest extends TestCase
{
    public function test_gemini_api_key_is_sent_in_a_header_and_never_in_the_probe_url(): void
    {
        $check = new class extends AiProviderHealthCheck
        {
            public function probeUrlFor(string $provider, array $config): ?string
            {
                return $this->probeUrl($provider, $config);
            }

            public function headersFor(string $provider, string $key): array
            {
                return $this->headers($provider, $key);
            }
        };

        $url = $check->probeUrlFor('gemini', [
            'key' => 'SUPER-SECRET-GEMINI-KEY',
            'url' => 'https://generativelanguage.test/v1beta',
        ]);

        $this->assertSame('https://generativelanguage.test/v1beta/models', $url);
        $this->assertStringNotContainsString('SUPER-SECRET-GEMINI-KEY', (string) $url);

        $this->assertSame(
            ['x-goog-api-key' => 'SUPER-SECRET-GEMINI-KEY'],
            $check->headersFor('gemini', 'SUPER-SECRET-GEMINI-KEY'),
        );

        $this->assertSame(
            ['Authorization' => 'Bearer OPENAI-KEY'],
            $check->headersFor('openai', 'OPENAI-KEY'),
        );
    }

    public function test_ai_probe_does_not_leak_the_key_in_the_request_url(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        config([
            'ai.default' => 'gemini',
            'ai.providers.gemini.key' => 'LEAKY-GEMINI-KEY',
            'ai.providers.gemini.url' => 'https://generativelanguage.test/v1beta',
            'health.checks.ai.provider.probe' => true,
        ]);

        (new AiProviderHealthCheck)->run();

        Http::assertSent(function ($request) {
            return ! str_contains($request->url(), 'LEAKY-GEMINI-KEY')
                && $request->hasHeader('x-goog-api-key', 'LEAKY-GEMINI-KEY');
        });
    }

    public function test_widget_rate_limit_key_ignores_a_rotating_session_token(): void
    {
        $middleware = new WidgetRateLimit(app(RateLimiter::class));

        $resolve = (new \ReflectionMethod($middleware, 'resolveRequestSignature'));
        $resolve->setAccessible(true);
        $signature = fn (Request $request): string => (string) $resolve->invoke($middleware, $request);

        $first = Request::create('/api/v1/widget/chat', 'POST', ['session_token' => 'attacker-token-1']);
        $second = Request::create('/api/v1/widget/chat', 'POST', ['session_token' => 'attacker-token-2']);

        $this->assertSame(
            $signature($first),
            $signature($second),
            'a caller-supplied session_token must not create a fresh rate-limit bucket',
        );

        $differentPath = Request::create('/api/v1/widget/other', 'POST', ['session_token' => 'attacker-token-1']);
        $this->assertNotSame($signature($first), $signature($differentPath));

        $differentIp = Request::create('/api/v1/widget/chat', 'POST', [], [], [], ['REMOTE_ADDR' => '10.9.9.9']);
        $this->assertNotSame($signature($first), $signature($differentIp));
    }

    public function test_security_headers_are_added_and_do_not_overwrite_existing_values(): void
    {
        $middleware = new SecurityHeaders;

        $response = $middleware->handle(Request::create('/api/v1/ping', 'GET'), function () {
            $response = new Response('ok');
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

            return $response;
        });

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'), 'existing headers must win');
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertSame('none', $response->headers->get('X-Permitted-Cross-Domain-Policies'));
        $this->assertFalse($response->headers->has('Strict-Transport-Security'), 'HSTS only over HTTPS');
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $middleware = new SecurityHeaders;

        $response = $middleware->handle(Request::create('https://api.example.test/ping', 'GET'), fn () => new Response('ok'));

        $this->assertSame('max-age=31536000; includeSubDomains', $response->headers->get('Strict-Transport-Security'));
    }
}
