<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailVerificationMail;
use Illuminate\Support\Facades\Mail;
use Tests\Support\InteractsWithAuth;
use Tests\TestCase;

/**
 * Covers the email verification lifecycle: verification links, resending, and
 * the login gate for unverified accounts.
 */
class EmailVerificationTest extends TestCase
{
    use InteractsWithAuth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootAuthEnvironment();

        config(['app.frontend_url' => 'https://app.example.test']);
    }

    public function test_verification_link_marks_the_email_verified_and_allows_login(): void
    {
        [$user] = $this->makeTenantOwner(['email_verified_at' => null]);

        $hash = sha1($user->getEmailForVerification());

        $this->getJson("/api/v1/auth/verify-email/{$user->id}/{$hash}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull($user->fresh()->email_verified_at, 'the email must now be verified');

        // Once verified, the account can authenticate normally.
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'SecretPass!123',
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_verification_link_rejects_an_invalid_hash(): void
    {
        [$user] = $this->makeTenantOwner(['email_verified_at' => null]);

        $this->getJson("/api/v1/auth/verify-email/{$user->id}/not-a-valid-hash")
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertNull($user->fresh()->email_verified_at, 'a bad hash must not verify the email');
    }

    public function test_login_is_blocked_for_an_unverified_user(): void
    {
        [$user] = $this->makeTenantOwner(['email_verified_at' => null]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'SecretPass!123',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'email_not_verified');

        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_resend_verification_emails_a_link_for_an_unverified_account(): void
    {
        Mail::fake();

        [$user] = $this->makeTenantOwner(['email_verified_at' => null]);

        $this->postJson('/api/v1/auth/resend-verification', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertQueued(EmailVerificationMail::class, function (EmailVerificationMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && str_contains($mail->verifyUrl, "/verify-email/{$user->id}/");
        });
    }

    public function test_resend_verification_does_not_reveal_unknown_emails(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/resend-verification', [
            'email' => 'nobody@example.test',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertNothingQueued();
    }

    public function test_resend_verification_is_a_noop_for_a_verified_account(): void
    {
        Mail::fake();

        [$user] = $this->makeTenantOwner();

        $this->postJson('/api/v1/auth/resend-verification', [
            'email' => $user->email,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertNothingQueued();
    }
}
