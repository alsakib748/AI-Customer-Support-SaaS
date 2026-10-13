<?php

namespace Tests\Feature\Ops;

use Tests\TestCase;

class ProductionCheckCommandTest extends TestCase
{
    public function test_a_safe_production_like_configuration_passes(): void
    {
        $this->applySafeProductionConfig();

        $this->artisan('app:production-check', ['--allow-non-production' => true])
            ->expectsOutputToContain('Production configuration check passed')
            ->assertExitCode(0);
    }

    public function test_debug_mode_fails_the_check(): void
    {
        $this->applySafeProductionConfig();
        config(['app.debug' => true]);

        $this->artisan('app:production-check', ['--allow-non-production' => true])
            ->expectsOutputToContain('APP_DEBUG')
            ->assertExitCode(1);
    }

    public function test_wildcard_cors_fails_the_check(): void
    {
        $this->applySafeProductionConfig();
        config(['cors.allowed_origins' => ['*']]);

        $this->artisan('app:production-check', ['--allow-non-production' => true])
            ->expectsOutputToContain('CORS_ALLOWED_ORIGINS')
            ->assertExitCode(1);
    }

    public function test_check_fails_without_allow_flag_when_not_production(): void
    {
        $this->applySafeProductionConfig();
        config(['app.env' => 'local']);

        $this->artisan('app:production-check')
            ->expectsOutputToContain('APP_ENV')
            ->assertExitCode(1);
    }

    public function test_check_tolerates_a_missing_plan_price_table(): void
    {
        $this->applySafeProductionConfig();

        // The central schema in tests does not include plan_provider_prices; the
        // command must degrade gracefully rather than crash during pre-deploy.
        $this->artisan('app:production-check', ['--allow-non-production' => true])
            ->assertExitCode(0);
    }

    private function applySafeProductionConfig(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:not-a-real-key-but-present',
            'app.url' => 'https://app.example.test',
            'jwt.secret' => 'jwt-secret-value',
            'database.default' => 'pgsql',
            'cache.default' => 'redis',
            'filesystems.default' => 's3',
            'mail.default' => 'smtp',
            'mail.from.address' => 'no-reply@app.example.test',
            'cors.allowed_origins' => ['https://app.example.test'],
            'session.secure' => true,
            'session.encrypt' => true,
            'billing.providers.stripe.enabled' => false,
            'billing.providers.paypal.enabled' => false,
            'ai.features.enabled' => false,
        ]);
    }
}
