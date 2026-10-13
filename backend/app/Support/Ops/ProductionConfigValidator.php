<?php

namespace App\Support\Ops;

/**
 * Pure, side-effect free validator for a resolved production configuration
 * snapshot. It never reads files, env or the database so it can be unit tested
 * deterministically. The calling command is responsible for building the
 * snapshot from config() and for appending infrastructure findings (e.g. plan
 * price mappings).
 *
 * Each returned issue is:
 *   ['level' => 'error'|'warning', 'key' => string, 'message' => string]
 *
 * An 'error' issue means the configuration is not safe to deploy.
 */
class ProductionConfigValidator
{
    /**
     * @param  array<string, mixed>  $config
     * @param  bool  $allowNonProduction  When true, APP_ENV is not required to be
     *                                    "production" and production-only rules
     *                                    are downgraded to warnings.
     * @return array<int, array{level: string, key: string, message: string}>
     */
    public function validate(array $config, bool $allowNonProduction = false): array
    {
        $issues = [];
        $environment = (string) ($config['environment'] ?? '');
        $isProduction = $environment === 'production';

        // When the check runs outside production without the explicit opt-in,
        // the environment itself is the failure. Production-only rules are
        // still evaluated so an operator can preview them.
        if (! $isProduction) {
            $issues[] = [
                'level' => $allowNonProduction ? 'warning' : 'error',
                'key' => 'APP_ENV',
                'message' => "Application environment is \"{$environment}\"; production deployments must set APP_ENV=production.",
            ];
        }

        $level = fn (bool $productionOnly): string => $isProduction ? 'error' : 'warning';

        $this->checkCore($issues, $config, $level);
        $this->checkDatastores($issues, $config, $level);
        $this->checkMail($issues, $config, $level);
        $this->checkSession($issues, $config, $level);
        $this->checkCors($issues, $config, $level);
        $this->checkPayments($issues, $config, $level);
        $this->checkAi($issues, $config);
        $this->checkBotProtection($issues, $config);

        return $issues;
    }

    private function checkCore(array &$issues, array $config, callable $level): void
    {
        if (($config['debug'] ?? false) === true) {
            $issues[] = [
                'level' => 'error',
                'key' => 'APP_DEBUG',
                'message' => 'Debug mode is enabled; stack traces and configuration will leak to clients.',
            ];
        }

        if (empty($config['key'])) {
            $issues[] = [
                'level' => 'error',
                'key' => 'APP_KEY',
                'message' => 'APP_KEY is empty. Run: php artisan key:generate.',
            ];
        }

        if (empty($config['jwt_secret'])) {
            $issues[] = [
                'level' => 'error',
                'key' => 'JWT_SECRET',
                'message' => 'JWT secret is not configured. Run: php artisan jwt:secret.',
            ];
        }

        $url = (string) ($config['app_url'] ?? '');
        if (! str_starts_with($url, 'https://')) {
            $issues[] = [
                'level' => $level(true),
                'key' => 'APP_URL',
                'message' => 'APP_URL must use https:// so generated links and redirects are secure.',
            ];
        }
    }

    private function checkDatastores(array &$issues, array $config, callable $level): void
    {
        if (($config['database_default'] ?? null) === 'sqlite') {
            $issues[] = [
                'level' => $level(true),
                'key' => 'DB_CONNECTION',
                'message' => 'The application is using SQLite as its primary database; production requires a managed server database (e.g. PostgreSQL).',
            ];
        }

        if (($config['cache_default'] ?? null) === 'array') {
            $issues[] = [
                'level' => $level(false),
                'key' => 'CACHE_STORE',
                'message' => 'Array cache does not persist across requests and is unsafe for production.',
            ];
        }

        if (($config['filesystems_default'] ?? null) === 'local') {
            $issues[] = [
                'level' => $level(false),
                'key' => 'FILESYSTEM_DISK',
                'message' => 'Local disk storage is ephemeral in most deployments; use object storage for uploads.',
            ];
        }
    }

    private function checkMail(array &$issues, array $config, callable $level): void
    {
        $transport = (string) ($config['mail_default'] ?? '');
        if (in_array($transport, ['log', 'array', 'sendmail'], true)) {
            $issues[] = [
                'level' => $level(true),
                'key' => 'MAIL_MAILER',
                'message' => "Mail transport \"{$transport}\" does not deliver email; production must use smtp|ses|postmark|resend.",
            ];
        }

        $from = (string) ($config['mail_from'] ?? '');
        if ($from === '' || str_ends_with($from, '@example.com') || str_contains($from, 'example.com')) {
            $issues[] = [
                'level' => $level(true),
                'key' => 'MAIL_FROM_ADDRESS',
                'message' => 'MAIL_FROM_ADDRESS is empty or uses the reserved example.com domain.',
            ];
        }
    }

    private function checkSession(array &$issues, array $config, callable $level): void
    {
        if (($config['session_secure'] ?? false) !== true) {
            $issues[] = [
                'level' => $level(true),
                'key' => 'SESSION_SECURE_COOKIE',
                'message' => 'Session cookies are not marked Secure; set SESSION_SECURE_COOKIE=true behind HTTPS.',
            ];
        }

        if (($config['session_encrypt'] ?? false) !== true) {
            $issues[] = [
                'level' => $level(true),
                'key' => 'SESSION_ENCRYPT',
                'message' => 'Session payload encryption is disabled; enable SESSION_ENCRYPT=true in production.',
            ];
        }
    }

    private function checkCors(array &$issues, array $config, callable $level): void
    {
        $origins = array_values(array_filter((array) ($config['cors_origins'] ?? []), fn ($o) => $o !== null && $o !== ''));

        if (in_array('*', $origins, true)) {
            $issues[] = [
                'level' => 'error',
                'key' => 'CORS_ALLOWED_ORIGINS',
                'message' => 'Wildcard CORS origin "*" is not allowed.',
            ];
        }

        if (empty($origins)) {
            $issues[] = [
                'level' => $level(false),
                'key' => 'CORS_ALLOWED_ORIGINS',
                'message' => 'No CORS origins configured; set the production frontend origin(s).',
            ];
        }
    }

    private function checkPayments(array &$issues, array $config, callable $level): void
    {
        $stripe = (array) ($config['stripe'] ?? []);
        if (($stripe['enabled'] ?? false) === true) {
            foreach (['secret' => 'STRIPE_SECRET', 'webhook_secret' => 'STRIPE_WEBHOOK_SECRET'] as $field => $key) {
                if (empty($stripe[$field])) {
                    $issues[] = [
                        'level' => 'error',
                        'key' => $key,
                        'message' => "Stripe is enabled but {$key} is not configured.",
                    ];
                }
            }

            $secret = (string) ($stripe['secret'] ?? '');
            if ($level(true) === 'error' && str_starts_with($secret, 'sk_test')) {
                $issues[] = [
                    'level' => 'error',
                    'key' => 'STRIPE_SECRET',
                    'message' => 'Stripe secret looks like a test key (sk_test_...) in a production environment.',
                ];
            }
        }

        $paypal = (array) ($config['paypal'] ?? []);
        if (($paypal['enabled'] ?? false) === true) {
            foreach (['client_id' => 'PAYPAL_CLIENT_ID', 'client_secret' => 'PAYPAL_CLIENT_SECRET', 'webhook_id' => 'PAYPAL_WEBHOOK_ID'] as $field => $key) {
                if (empty($paypal[$field])) {
                    $issues[] = [
                        'level' => 'error',
                        'key' => $key,
                        'message' => "PayPal is enabled but {$key} is missing.",
                    ];
                }
            }

            $mode = (string) ($paypal['mode'] ?? 'sandbox');
            if ($mode !== 'live') {
                $issues[] = [
                    'level' => $level(true),
                    'key' => 'PAYPAL_MODE',
                    'message' => "PayPal mode is \"{$mode}\"; production must set PAYPAL_MODE=live.",
                ];
            }
        }
    }

    private function checkBotProtection(array &$issues, array $config): void
    {
        $turnstile = (array) ($config['turnstile'] ?? []);

        if (($turnstile['enabled'] ?? false) !== true) {
            $issues[] = [
                'level' => 'warning',
                'key' => 'TURNSTILE_ENABLED',
                'message' => 'Bot protection (Cloudflare Turnstile) is disabled; registration, login and password reset are exposed to automated abuse.',
            ];

            return;
        }

        if (empty($turnstile['secret_key'])) {
            $issues[] = [
                'level' => 'error',
                'key' => 'TURNSTILE_SECRET_KEY',
                'message' => 'Turnstile is enabled but TURNSTILE_SECRET_KEY is not configured.',
            ];
        }
    }

    private function checkAi(array &$issues, array $config): void
    {
        if (($config['ai_enabled'] ?? false) !== true) {
            return;
        }

        $providers = (array) ($config['ai_providers'] ?? []);
        $requiresKey = fn (string $provider): bool => ! in_array($provider, ['ollama', 'bedrock', 'openai-compatible'], true);

        $default = (string) ($config['ai_default_provider'] ?? '');
        if ($default !== '' && $requiresKey($default) && empty($providers[$default]['key'] ?? null)) {
            $issues[] = [
                'level' => 'error',
                'key' => "AI_PROVIDER ({$default})",
                'message' => "AI is enabled with default provider \"{$default}\" but its API key is not configured.",
            ];
        }

        if (($config['ai_embeddings_enabled'] ?? false) === true) {
            $embedding = (string) ($config['ai_embedding_provider'] ?? '');
            if ($embedding !== '' && $requiresKey($embedding) && empty($providers[$embedding]['key'] ?? null)) {
                $issues[] = [
                    'level' => 'error',
                    'key' => "AI_EMBEDDING_PROVIDER ({$embedding})",
                    'message' => "Embeddings are enabled with provider \"{$embedding}\" but its API key is not configured.",
                ];
            }
        }
    }
}
