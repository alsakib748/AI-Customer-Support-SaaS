<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\InteractsWithCentralSchema;
use Tests\TestCase;

class TurnstileProtectionTest extends TestCase
{
    use InteractsWithCentralSchema;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'central']);
        config(['tenancy.database.central_connection' => 'central']);
        config(['cache.default' => 'array']);
        DB::purge('central');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->createCentralSchema();
        $this->createDefaultPlan();
    }

    public function test_registration_is_allowed_without_a_token_when_bot_protection_is_disabled(): void
    {
        Http::fake();

        config(['services.turnstile.enabled' => false, 'services.turnstile.secret_key' => null]);

        $this->postJson('/api/v1/auth/register', $this->registrationPayload())
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        Http::assertNothingSent();
    }

    public function test_registration_is_rejected_without_a_token_when_bot_protection_is_enabled(): void
    {
        $this->enableBotProtection();
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $email = 'no-token@example.test';

        $this->postJson('/api/v1/auth/register', $this->registrationPayload(['email' => $email]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['captcha']]);

        // No side effects: the user must not be created when the captcha is missing.
        $this->assertSame(0, DB::connection('central')->table('users')->where('email', $email)->count());
        $this->assertSame(0, DB::connection('central')->table('tenants')->count());
        Http::assertNothingSent();
    }

    public function test_registration_is_rejected_when_verification_fails(): void
    {
        $this->enableBotProtection();
        Http::fake(['*' => Http::response([
            'success' => false,
            'error-codes' => ['invalid-input-response'],
        ], 200)]);

        $email = 'bad-token@example.test';

        $this->postJson('/api/v1/auth/register', $this->registrationPayload([
            'email' => $email,
            'turnstile_token' => 'invalid-token',
        ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['captcha']]);

        $this->assertSame(0, DB::connection('central')->table('users')->where('email', $email)->count());
    }

    public function test_registration_proceeds_when_verification_succeeds(): void
    {
        $this->enableBotProtection();
        Http::fake(['*' => Http::response(['success' => true, 'hostname' => 'example.test'], 200)]);

        $email = 'good-token@example.test';

        $this->postJson('/api/v1/auth/register', $this->registrationPayload([
            'email' => $email,
            'turnstile_token' => 'valid-token',
        ]))
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertSame(1, DB::connection('central')->table('users')->where('email', $email)->count());
    }

    public function test_registration_accepts_the_token_from_a_header(): void
    {
        $this->enableBotProtection();
        Http::fake(['*' => Http::response(['success' => true, 'hostname' => 'example.test'], 200)]);

        $email = 'header-token@example.test';

        $this->withHeader('X-Turnstile-Token', 'valid-token')
            ->postJson('/api/v1/auth/register', $this->registrationPayload(['email' => $email]))
            ->assertStatus(201);

        $this->assertSame(1, DB::connection('central')->table('users')->where('email', $email)->count());
    }

    public function test_verification_request_sends_the_secret_and_token(): void
    {
        $this->enableBotProtection();
        Http::fake(['*' => Http::response(['success' => true, 'hostname' => 'example.test'], 200)]);

        $this->postJson('/api/v1/auth/register', $this->registrationPayload([
            'turnstile_token' => 'the-token',
        ]))->assertStatus(201);

        Http::assertSent(function ($request) {
            return $request->url() === config('services.turnstile.verify_url')
                && $request['secret'] === 'test-secret'
                && $request['response'] === 'the-token';
        });
    }

    public function test_registration_rejects_a_token_issued_for_an_unexpected_hostname(): void
    {
        $this->enableBotProtection(['services.turnstile.expected_hostname' => 'app.example.test']);
        Http::fake(['*' => Http::response(['success' => true, 'hostname' => 'evil.example.test'], 200)]);

        $email = 'wrong-host@example.test';

        $this->postJson('/api/v1/auth/register', $this->registrationPayload([
            'email' => $email,
            'turnstile_token' => 'valid-token',
        ]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['captcha']]);

        $this->assertSame(0, DB::connection('central')->table('users')->where('email', $email)->count());
    }

    public function test_login_requires_a_token_when_bot_protection_is_enabled(): void
    {
        $this->enableBotProtection();
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'someone@example.test',
            'password' => 'SecretPass!123',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['captcha']]);

        Http::assertNothingSent();
    }

    public function test_login_reaches_credential_validation_once_the_captcha_passes(): void
    {
        $this->enableBotProtection();
        Http::fake(['*' => Http::response(['success' => true, 'hostname' => 'example.test'], 200)]);

        // No such user: the failure must be a credential validation error, which
        // proves the request passed the captcha middleware.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'ghost@example.test',
            'password' => 'SecretPass!123',
            'turnstile_token' => 'valid-token',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email']])
            ->assertJsonMissingPath('errors.captcha');
    }

    public function test_forgot_password_requires_a_token_when_bot_protection_is_enabled(): void
    {
        $this->enableBotProtection();
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'someone@example.test'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['captcha']]);

        Http::assertNothingSent();
    }

    public function test_forgot_password_proceeds_once_the_captcha_passes(): void
    {
        $this->enableBotProtection();
        Http::fake(['*' => Http::response(['success' => true, 'hostname' => 'example.test'], 200)]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'someone@example.test',
            'turnstile_token' => 'valid-token',
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function enableBotProtection(array $overrides = []): void
    {
        config(array_merge([
            'services.turnstile.enabled' => true,
            'services.turnstile.secret_key' => 'test-secret',
            'services.turnstile.site_key' => 'test-site-key',
            'services.turnstile.verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'services.turnstile.expected_hostname' => null,
            'services.turnstile.timeout' => 5,
        ], $overrides));
    }
}
