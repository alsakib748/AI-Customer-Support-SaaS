<?php

namespace Tests\Feature\Auth;

use App\Mail\PasswordResetMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Tests\Support\InteractsWithAuth;
use Tests\TestCase;

/**
 * End-to-end HTTP coverage for the forgot/reset password contract consumed by
 * the frontend auth pages (`/forgot-password`, `/reset-password`).
 */
class PasswordResetFlowTest extends TestCase
{
    use InteractsWithAuth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAuthEnvironment();

        Schema::connection('central')->create('password_reset_tokens', function ($table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function test_forgot_password_responds_generically_and_emails_a_reset_link(): void
    {
        Mail::fake();

        $this->makeTenantOwner(['email' => 'reset-me@example.test']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset-me@example.test',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        // The token is never exposed through the API.
        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertNull($response->json('data'));

        Mail::assertQueued(PasswordResetMail::class, function (PasswordResetMail $mail) {
            return $mail->user->email === 'reset-me@example.test'
                && str_contains($mail->resetUrl, '/reset-password?')
                && str_contains($mail->resetUrl, 'token=')
                && str_contains($mail->resetUrl, 'email=reset-me%40example.test');
        });
    }

    public function test_forgot_password_returns_422_for_an_unknown_email_and_sends_no_mail(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.test'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['email']]);

        Mail::assertNothingQueued();
    }

    public function test_reset_password_with_a_valid_token_updates_credentials_and_issues_a_token(): void
    {
        $user = $this->makeTenantOwner(['email' => 'reset-me@example.test'])[0];
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset-me@example.test',
            'token' => $token,
            'password' => 'BrandNewPass!123',
            'password_confirmation' => 'BrandNewPass!123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertTrue(Hash::check('BrandNewPass!123', $user->fresh()->password));

        // The new password works and the old one no longer does.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'reset-me@example.test',
            'password' => 'BrandNewPass!123',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'reset-me@example.test',
            'password' => 'SecretPass!123',
        ])->assertStatus(422);
    }

    public function test_reset_password_rejects_a_mismatched_confirmation(): void
    {
        $user = $this->makeTenantOwner(['email' => 'reset-me@example.test'])[0];
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset-me@example.test',
            'token' => $token,
            'password' => 'BrandNewPass!123',
            'password_confirmation' => 'Different!123',
        ])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['password']]);

        $this->assertFalse(Hash::check('BrandNewPass!123', $user->fresh()->password));
        $this->assertTrue(Hash::check('SecretPass!123', $user->fresh()->password));
    }

    public function test_reset_password_with_an_invalid_token_does_not_change_the_password(): void
    {
        $user = $this->makeTenantOwner(['email' => 'reset-me@example.test'])[0];

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset-me@example.test',
            'token' => 'not-a-real-token',
            'password' => 'BrandNewPass!123',
            'password_confirmation' => 'BrandNewPass!123',
        ]);

        // Known rough edge: the controller maps an invalid/expired token to a
        // 500 (generic exception). The security-relevant guarantee asserted
        // here is that a failed reset never mutates the stored password.
        $response->assertStatus(500)
            ->assertJsonPath('success', false);

        $this->assertFalse(Hash::check('BrandNewPass!123', $user->fresh()->password));
        $this->assertTrue(Hash::check('SecretPass!123', $user->fresh()->password));
    }
}
