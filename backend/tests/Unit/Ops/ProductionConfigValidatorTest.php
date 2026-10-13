<?php

namespace Tests\Unit\Ops;

use App\Support\Ops\ProductionConfigValidator;
use Tests\TestCase;

class ProductionConfigValidatorTest extends TestCase
{
    public function test_a_safe_production_configuration_reports_no_issues(): void
    {
        $this->assertSame([], $this->validate([]));
    }

    public function test_debug_mode_is_an_error(): void
    {
        $this->assertError($this->validate(['debug' => true]), 'APP_DEBUG');
    }

    public function test_empty_application_key_is_an_error(): void
    {
        $this->assertError($this->validate(['key' => '']), 'APP_KEY');
    }

    public function test_missing_jwt_secret_is_an_error(): void
    {
        $this->assertError($this->validate(['jwt_secret' => '']), 'JWT_SECRET');
    }

    public function test_non_https_app_url_is_an_error_in_production(): void
    {
        $this->assertError($this->validate(['app_url' => 'http://example.test']), 'APP_URL');
    }

    public function test_sqlite_primary_database_is_an_error_in_production(): void
    {
        $this->assertError($this->validate(['database_default' => 'sqlite']), 'DB_CONNECTION');
    }

    public function test_log_mail_transport_is_an_error_in_production(): void
    {
        $this->assertError($this->validate(['mail_default' => 'log']), 'MAIL_MAILER');
    }

    public function test_example_com_mail_from_is_an_error_in_production(): void
    {
        $this->assertError($this->validate(['mail_from' => 'hello@example.com']), 'MAIL_FROM_ADDRESS');
    }

    public function test_insecure_session_cookies_are_an_error_in_production(): void
    {
        $this->assertError($this->validate(['session_secure' => false]), 'SESSION_SECURE_COOKIE');
    }

    public function test_wildcard_cors_origin_is_always_an_error(): void
    {
        $this->assertError(
            $this->validate(['cors_origins' => ['*']], allowNonProduction: true),
            'CORS_ALLOWED_ORIGINS',
        );
    }

    public function test_enabled_stripe_without_webhook_secret_is_an_error(): void
    {
        $issues = $this->validate([
            'stripe' => ['enabled' => true, 'secret' => 'sk_live_abc', 'webhook_secret' => ''],
        ]);

        $this->assertError($issues, 'STRIPE_WEBHOOK_SECRET');
    }

    public function test_stripe_test_key_in_production_is_an_error(): void
    {
        $issues = $this->validate([
            'stripe' => ['enabled' => true, 'secret' => 'sk_test_abc', 'webhook_secret' => 'whsec_abc'],
        ]);

        $this->assertError($issues, 'STRIPE_SECRET');
    }

    public function test_paypal_sandbox_mode_is_an_error_in_production(): void
    {
        $issues = $this->validate([
            'paypal' => [
                'enabled' => true,
                'client_id' => 'id',
                'client_secret' => 'secret',
                'webhook_id' => 'wh',
                'mode' => 'sandbox',
            ],
        ]);

        $this->assertError($issues, 'PAYPAL_MODE');
    }

    public function test_enabled_ai_without_provider_key_is_an_error(): void
    {
        $issues = $this->validate([
            'ai_enabled' => true,
            'ai_default_provider' => 'openai',
            'ai_providers' => ['openai' => ['key' => '']],
        ]);

        $this->assertError($issues, 'AI_PROVIDER (openai)');
    }

    public function test_disabled_bot_protection_is_a_warning_in_production(): void
    {
        $issues = $this->validate(['turnstile' => ['enabled' => false]]);

        $this->assertTrue(
            collect($issues)->contains(fn ($issue) => $issue['key'] === 'TURNSTILE_ENABLED' && $issue['level'] === 'warning'),
            'Expected a warning-level TURNSTILE_ENABLED issue when bot protection is disabled.',
        );
    }

    public function test_enabled_bot_protection_without_secret_key_is_an_error(): void
    {
        $issues = $this->validate(['turnstile' => ['enabled' => true, 'secret_key' => '']]);

        $this->assertError($issues, 'TURNSTILE_SECRET_KEY');
    }

    public function test_non_production_environment_is_an_error_without_opt_in(): void
    {
        $this->assertError($this->validate(['environment' => 'local']), 'APP_ENV');
    }

    public function test_non_production_environment_is_a_warning_with_opt_in(): void
    {
        $issues = $this->validate(['environment' => 'local'], allowNonProduction: true);

        $this->assertTrue(
            collect($issues)->contains(fn ($issue) => $issue['key'] === 'APP_ENV' && $issue['level'] === 'warning'),
            'Expected a warning-level APP_ENV issue when --allow-non-production is set.',
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<int, array{level: string, key: string, message: string}>
     */
    private function validate(array $overrides, bool $allowNonProduction = false): array
    {
        return (new ProductionConfigValidator)->validate(
            array_merge($this->validConfig(), $overrides),
            $allowNonProduction,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validConfig(): array
    {
        return [
            'environment' => 'production',
            'debug' => false,
            'key' => 'base64:not-a-real-key-but-present',
            'jwt_secret' => 'jwt-secret-value',
            'app_url' => 'https://app.example.test',
            'database_default' => 'pgsql',
            'cache_default' => 'redis',
            'filesystems_default' => 's3',
            'mail_default' => 'smtp',
            'mail_from' => 'no-reply@app.example.test',
            'cors_origins' => ['https://app.example.test'],
            'session_secure' => true,
            'session_encrypt' => true,
            'turnstile' => ['enabled' => true, 'secret_key' => 'ts-secret'],
            'stripe' => ['enabled' => false],
            'paypal' => ['enabled' => false],
            'ai_enabled' => false,
            'ai_default_provider' => 'openai',
            'ai_embeddings_enabled' => false,
            'ai_embedding_provider' => 'openai',
            'ai_providers' => [],
        ];
    }

    /**
     * @param  array<int, array{level: string, key: string, message: string}>  $issues
     */
    private function assertError(array $issues, string $key): void
    {
        $this->assertTrue(
            collect($issues)->contains(fn ($issue) => $issue['key'] === $key && $issue['level'] === 'error'),
            "Expected an error-level issue for [{$key}].",
        );
    }
}
