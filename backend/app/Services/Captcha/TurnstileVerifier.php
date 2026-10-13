<?php

namespace App\Services\Captcha;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies Cloudflare Turnstile tokens server-side.
 *
 * This is the only place that talks to the Turnstile siteverify endpoint. It
 * fails closed: any network error, malformed response or missing token is
 * treated as a failed verification. When the integration is not enabled (or no
 * secret key is configured) verification is a no-op so local development and
 * the automated test suite never make external calls.
 */
class TurnstileVerifier
{
    /**
     * Whether bot protection is active for this environment.
     */
    public function enabled(): bool
    {
        return (bool) config('services.turnstile.enabled')
            && filled(config('services.turnstile.secret_key'));
    }

    /**
     * Verify a Turnstile response token.
     *
     * Returns true when verification is disabled, when the token is valid, or
     * when the token's hostname is in the configured allow-list.
     */
    public function verify(?string $token, ?string $ip = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        $payload = [
            'secret' => (string) config('services.turnstile.secret_key'),
            'response' => $token,
        ];

        if (filled($ip)) {
            $payload['remoteip'] = $ip;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('services.turnstile.timeout', 5))
                ->post((string) config('services.turnstile.verify_url'), $payload);
        } catch (ConnectionException $e) {
            Log::warning('Turnstile verification request failed', ['error' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Turnstile verification returned a non-success status', [
                'status' => $response->status(),
            ]);

            return false;
        }

        $body = (array) $response->json();

        if (($body['success'] ?? false) !== true) {
            Log::info('Turnstile verification rejected a token', [
                'error_codes' => $body['error-codes'] ?? [],
            ]);

            return false;
        }

        return $this->hostnameAllowed($body['hostname'] ?? null);
    }

    /**
     * Enforce the optional hostname allow-list.
     */
    private function hostnameAllowed(?string $hostname): bool
    {
        $expected = trim((string) config('services.turnstile.expected_hostname'));

        if ($expected === '') {
            return true;
        }

        if (blank($hostname)) {
            return false;
        }

        $allowed = array_filter(array_map('trim', explode(',', $expected)));

        if (! in_array($hostname, $allowed, true)) {
            Log::warning('Turnstile token hostname is not allowed', ['hostname' => $hostname]);

            return false;
        }

        return true;
    }
}
