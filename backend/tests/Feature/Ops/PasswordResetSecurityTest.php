<?php

namespace Tests\Feature\Ops;

use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Support\InteractsWithCentralSchema;
use Tests\TestCase;

/**
 * Phase 6 WS3 regression: password reset must not leak a reset token in the API
 * response and must not reveal whether an email is registered.
 */
class PasswordResetSecurityTest extends TestCase
{
    use InteractsWithCentralSchema;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'central']);

        $this->createCentralSchema();

        Schema::connection('central')->create('password_reset_tokens', function ($table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    private function makeUser(string $email): User
    {
        return User::query()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => $email,
            'password' => 'SecretPass!123',
            'is_active' => true,
        ]);
    }

    public function test_forgot_password_returns_a_generic_message_without_a_token(): void
    {
        Mail::fake();
        $this->makeUser('known@example.test');

        $service = app(AuthService::class);

        $known = $service->forgotPassword(['email' => 'known@example.test']);
        $unknown = $service->forgotPassword(['email' => 'nobody@example.test']);

        $this->assertArrayNotHasKey('token', $known);
        $this->assertArrayNotHasKey('data', $known);
        $this->assertSame(
            $known['message'],
            $unknown['message'],
            'the response must be identical for known and unknown emails',
        );
    }

    public function test_forgot_password_sends_the_reset_mail_with_a_frontend_url(): void
    {
        Mail::fake();
        $this->makeUser('known@example.test');

        app(AuthService::class)->forgotPassword(['email' => 'known@example.test']);

        Mail::assertQueued(PasswordResetMail::class, function (PasswordResetMail $mail) {
            return $mail->user->email === 'known@example.test'
                && str_contains($mail->resetUrl, 'reset-password')
                && str_contains($mail->resetUrl, 'token=')
                && str_contains($mail->resetUrl, 'email=known%40example.test');
        });
    }

    public function test_forgot_password_sends_nothing_for_an_unknown_email(): void
    {
        Mail::fake();
        $this->makeUser('known@example.test');

        app(AuthService::class)->forgotPassword(['email' => 'nobody@example.test']);

        Mail::assertNothingQueued();
    }
}
