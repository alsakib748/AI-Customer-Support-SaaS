<?php

namespace App\Health\Checks;

use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * A configured API key is not evidence the provider is healthy. When enabled,
 * this check performs a tiny, authenticated, read-only probe against the
 * default provider's models endpoint (bounded timeout, no generation costs).
 * Providers without a supported probe endpoint are reported as "unknown"
 * (data pending) instead of being assumed healthy.
 */
class AiProviderHealthCheck extends AbstractHealthCheck
{
    public function key(): string
    {
        return 'ai.provider';
    }

    protected function perform(): HealthCheckResult
    {
        $provider = (string) config('ai.default', 'openai');
        $providerConfig = (array) (config('ai.providers.'.$provider, []));
        $key = (string) ($providerConfig['key'] ?? '');

        $metadata = ['provider' => $provider];

        if ($key === '') {
            return HealthCheckResult::make(
                $this->key(),
                HealthStatus::Disabled,
                "No API key configured for '{$provider}'",
                $metadata,
            );
        }

        if (! (bool) $this->config('probe', true)) {
            return HealthCheckResult::make(
                $this->key(),
                HealthStatus::Unknown,
                "Key configured for '{$provider}'; probes disabled",
                $metadata + ['probe' => false],
            );
        }

        $url = $this->probeUrl($provider, $providerConfig);

        if ($url === null) {
            return HealthCheckResult::make(
                $this->key(),
                HealthStatus::Unknown,
                "No probe endpoint available for '{$provider}'",
                $metadata + ['probe' => 'unsupported'],
            );
        }

        $timeout = (int) $this->config('timeout', 5);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders($this->headers($provider, $key))
                ->acceptJson()
                ->get($url);
        } catch (ConnectionException $e) {
            return HealthCheckResult::make(
                $this->key(),
                HealthStatus::Unhealthy,
                'Could not reach AI provider',
                $metadata + ['probe' => 'connection_failed'],
            );
        }

        $metadata['probe'] = [
            'url' => $url,
            'status' => $response->status(),
        ];

        if ($response->successful()) {
            return HealthCheckResult::make($this->key(), HealthStatus::Healthy, "AI provider '{$provider}' reachable", $metadata);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return HealthCheckResult::make($this->key(), HealthStatus::Unhealthy, "AI provider '{$provider}' rejected the API key", $metadata);
        }

        if ($response->status() === 429) {
            return HealthCheckResult::make($this->key(), HealthStatus::Degraded, "AI provider '{$provider}' is rate-limiting", $metadata);
        }

        if ($response->serverError()) {
            return HealthCheckResult::make($this->key(), HealthStatus::Unhealthy, "AI provider '{$provider}' returned an error", $metadata);
        }

        return HealthCheckResult::make($this->key(), HealthStatus::Degraded, "AI provider '{$provider}' returned status {$response->status()}", $metadata);
    }

    /**
     * Resolve a read-only endpoint for the provider, or null when unsupported.
     */
    protected function probeUrl(string $provider, array $providerConfig): ?string
    {
        $paths = (array) $this->config('probe_paths', []);

        if (! isset($paths[$provider])) {
            return null;
        }

        $defaultBase = [
            'openai' => 'https://api.openai.com/v1',
            'groq' => 'https://api.groq.com/openai/v1',
            'anthropic' => 'https://api.anthropic.com/v1',
            'gemini' => 'https://generativelanguage.googleapis.com/v1beta',
            'ollama' => 'http://localhost:11434',
        ];

        $base = rtrim((string) ($providerConfig['url'] ?? $defaultBase[$provider] ?? ''), '/');

        if ($provider === 'gemini') {
            // The API key is sent via the x-goog-api-key header, never the URL,
            // so it cannot leak through access logs or stored metadata.
            return $base.'/models';
        }

        if ($provider === 'ollama') {
            return $base.'/api/tags';
        }

        return $base.'/'.$paths[$provider];
    }

    protected function headers(string $provider, string $key): array
    {
        if ($provider === 'anthropic') {
            return [
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
            ];
        }

        if ($provider === 'gemini') {
            return ['x-goog-api-key' => $key];
        }

        if ($provider === 'ollama') {
            return [];
        }

        return ['Authorization' => 'Bearer '.$key];
    }
}
