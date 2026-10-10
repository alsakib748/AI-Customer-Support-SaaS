<?php

namespace App\Services\SystemSettings;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Reports the health/connection status of external providers.
 *
 * Configuration checks only prove that credentials are present; "live"
 * probes perform a real, safe provider request and never fabricate success.
 * Secrets are never returned — only booleans and status strings.
 */
class IntegrationStatusService
{
    public function __construct(
        protected SystemSettingsService $settings,
    ) {
    }

    public function catalog(): array
    {
        return (array) config('system_settings.integrations_catalog', []);
    }

    public function has(string $provider): bool
    {
        return array_key_exists($provider, $this->catalog());
    }

    /**
     * Overview of every catalog provider.
     *
     * @return array<int,array<string,mixed>>
     */
    public function status(): array
    {
        return collect($this->catalog())
            ->map(fn (array $meta, string $provider) => array_merge(
                ['provider' => $provider],
                $this->statusFor($provider, $meta),
            ))
            ->values()
            ->all();
    }

    protected function statusFor(string $provider, array $meta): array
    {
        return match ($meta['driver'] ?? $provider) {
            'stripe'     => $this->stripeStatus(),
            'paypal'     => $this->paypalStatus(),
            'mail'       => $this->mailStatus(),
            'filesystem' => $this->storageStatus(),
            'ai'         => $this->aiStatus(),
            default      => [
                'label'      => $meta['label'] ?? ucfirst($provider),
                'configured' => false,
                'status'     => 'unknown',
                'method'     => 'configuration',
                'detail'     => 'No status probe implemented.',
                'docs'       => $meta['docs'] ?? null,
            ],
        };
    }

    /**
     * Perform a real connection test for a provider.
     */
    public function test(string $provider): array
    {
        $meta = $this->catalog()[$provider] ?? null;

        if (! $meta) {
            return [
                'provider' => $provider,
                'success'  => false,
                'status'   => 'error',
                'method'   => 'configuration',
                'message'  => 'Unknown integration provider.',
            ];
        }

        return match ($meta['driver'] ?? $provider) {
            'stripe'     => $this->testStripe(),
            'paypal'     => $this->testPaypal(),
            'mail'       => $this->testMail(),
            'filesystem' => $this->testStorage(),
            'ai'         => $this->testAi(),
            default      => [
                'provider' => $provider,
                'success'  => false,
                'status'   => 'error',
                'method'   => 'configuration',
                'message'  => 'No connection test implemented for this provider.',
            ],
        };
    }

    // =========================================================
    // STRIPE
    // =========================================================

    protected function stripeStatus(): array
    {
        $configured = ! empty(env('STRIPE_SECRET'));

        return [
            'label'      => 'Stripe',
            'configured' => $configured,
            'status'     => $configured ? 'configured' : 'not_configured',
            'method'     => 'live',
            'detail'     => $configured ? 'Secret key present.' : 'Missing STRIPE_SECRET.',
        ];
    }

    protected function testStripe(): array
    {
        $secret = env('STRIPE_SECRET');

        if (empty($secret)) {
            return $this->result('stripe', false, 'live', 'Stripe secret key is not configured.');
        }

        if (! class_exists(\Stripe\StripeClient::class)) {
            return $this->result('stripe', false, 'configuration', 'Stripe SDK is not installed.');
        }

        try {
            $client = new \Stripe\StripeClient($secret);
            $client->balance->retrieve();

            return $this->result('stripe', true, 'live', 'Connected to Stripe API successfully.');
        } catch (\Throwable $e) {
            return $this->result('stripe', false, 'live', 'Stripe API error: ' . $e->getMessage());
        }
    }

    // =========================================================
    // PAYPAL
    // =========================================================

    protected function paypalStatus(): array
    {
        $configured = ! empty(env('PAYPAL_CLIENT_ID')) && ! empty(env('PAYPAL_CLIENT_SECRET'));

        return [
            'label'      => 'PayPal',
            'configured' => $configured,
            'status'     => $configured ? 'configured' : 'not_configured',
            'method'     => 'live',
            'detail'     => $configured ? 'Client credentials present.' : 'Missing PayPal client credentials.',
        ];
    }

    protected function testPaypal(): array
    {
        $id     = env('PAYPAL_CLIENT_ID');
        $secret = env('PAYPAL_CLIENT_SECRET');

        if (empty($id) || empty($secret)) {
            return $this->result('paypal', false, 'live', 'PayPal credentials are not configured.');
        }

        try {
            $base = strtolower((string) env('PAYPAL_MODE', 'sandbox')) === 'live'
                ? 'https://api-m.paypal.com'
                : 'https://api-m.sandbox.paypal.com';

            $response = Http::asForm()
                ->withBasicAuth($id, $secret)
                ->timeout(10)
                ->post($base . '/v1/oauth2/token', ['grant_type' => 'client_credentials']);

            if ($response->successful() && $response->json('access_token')) {
                return $this->result('paypal', true, 'live', 'Obtained PayPal access token successfully.');
            }

            return $this->result('paypal', false, 'live', 'PayPal rejected the credentials (HTTP ' . $response->status() . ').');
        } catch (\Throwable $e) {
            return $this->result('paypal', false, 'live', 'PayPal connection error: ' . $e->getMessage());
        }
    }

    // =========================================================
    // EMAIL
    // =========================================================

    protected function mailStatus(): array
    {
        $mailer    = (string) config('mail.default');
        $from      = (string) config('mail.from.address');
        $configured = $mailer !== '' && $from !== '';

        return [
            'label'      => 'Email (SMTP)',
            'configured' => $configured,
            'status'     => $configured ? 'configured' : 'not_configured',
            'method'     => 'configuration',
            'detail'     => $configured
                ? sprintf('Mailer "%s", from %s.', $mailer, $from)
                : 'Mail transport is not fully configured.',
        ];
    }

    protected function testMail(): array
    {
        $status = $this->mailStatus();

        return $this->result(
            'email',
            $status['configured'],
            'configuration',
            $status['configured']
                ? 'Mail transport is configured. Use the "Send test email" action to verify real delivery.'
                : 'Mail transport is missing required configuration.'
        );
    }

    // =========================================================
    // STORAGE
    // =========================================================

    protected function storageStatus(): array
    {
        $driver = (string) config('filesystems.default');

        return [
            'label'      => 'Object Storage',
            'configured' => $driver !== '',
            'status'     => $driver !== '' ? 'configured' : 'not_configured',
            'method'     => 'live',
            'detail'     => sprintf('Default disk: %s', $driver ?: 'none'),
        ];
    }

    protected function testStorage(): array
    {
        $driver = (string) config('filesystems.default');

        try {
            $path = 'system-settings/healthcheck-' . uniqid() . '.txt';
            Storage::disk($driver)->put($path, 'ok');
            $read = Storage::disk($driver)->get($path);
            Storage::disk($driver)->delete($path);

            return $this->result(
                'storage',
                $read === 'ok',
                'live',
                $read === 'ok' ? 'Write/read round-trip succeeded on the default disk.' : 'Storage round-trip returned unexpected data.'
            );
        } catch (\Throwable $e) {
            return $this->result('storage', false, 'live', 'Storage error: ' . $e->getMessage());
        }
    }

    // =========================================================
    // AI
    // =========================================================

    protected function aiStatus(): array
    {
        $providers  = (array) config('ai.providers', []);
        $configured = collect($providers)
            ->filter(fn (array $p) => ! empty($p['key']))
            ->keys()
            ->values();

        return [
            'label'      => 'AI Provider',
            'configured' => $configured->isNotEmpty(),
            'status'     => $configured->isNotEmpty() ? 'configured' : 'not_configured',
            'method'     => 'configuration',
            'detail'     => $configured->isNotEmpty()
                ? 'Configured providers: ' . $configured->implode(', ')
                : 'No AI provider API key is configured.',
        ];
    }

    protected function testAi(): array
    {
        $default    = (string) $this->settings->get('ai.default_provider', config('ai.default'));
        $providers  = (array) config('ai.providers', []);
        $hasKey     = ! empty($providers[$default]['key']);

        return $this->result(
            'ai',
            $hasKey,
            'configuration',
            $hasKey
                ? sprintf('Default provider "%s" has an API key configured.', $default)
                : sprintf('Default provider "%s" is missing an API key.', $default)
        );
    }

    // =========================================================
    // HELPERS
    // =========================================================

    protected function result(string $provider, bool $success, string $method, string $message): array
    {
        return [
            'provider' => $provider,
            'success'  => $success,
            'status'   => $success ? 'ok' : 'error',
            'method'   => $method,
            'message'  => $message,
        ];
    }

    /**
     * Database connectivity probe (used by the operations health page).
     */
    public function databaseLatencyMs(): ?float
    {
        try {
            $start = microtime(true);
            DB::connection('central')->select('select 1');
            return round((microtime(true) - $start) * 1000, 2);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
