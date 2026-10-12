<?php

namespace Tests\Feature\Auth;

use Tests\Support\InteractsWithAuth;
use Tests\TestCase;

class SessionLifecycleTest extends TestCase
{
    use InteractsWithAuth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAuthEnvironment();
    }

    public function test_logout_invalidates_the_presented_token(): void
    {
        [$user] = $this->makeTenantOwner();
        $token = $this->loginToken($user->email);

        $this->withToken($token)->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_logout_without_a_token_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/logout')
            ->assertStatus(401);
    }

    public function test_refresh_rotates_the_token_and_invalidates_the_previous_one(): void
    {
        [$user] = $this->makeTenantOwner();
        $oldToken = $this->loginToken($user->email);

        $response = $this->withToken($oldToken)->postJson('/api/v1/auth/refresh');
        $response->assertOk()->assertJsonPath('success', true);
        $newToken = $response->json('data.token');

        $this->assertNotEmpty($newToken);
        $this->assertNotSame($token = $oldToken, $newToken);

        // The rotated (old) token must no longer be usable.
        $this->withToken($oldToken)->getJson('/api/v1/auth/me')->assertStatus(401);

        // The freshly minted token works.
        $this->withToken($newToken)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_refresh_without_a_token_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/refresh')
            ->assertStatus(401)
            ->assertJsonPath('error', 'token_absent');
    }

    public function test_a_token_issued_before_session_revocation_is_rejected(): void
    {
        [$user] = $this->makeTenantOwner();
        $token = $this->loginToken($user->email);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $user->setPreference('sessions_revoked_at', now()->addMinute()->toIso8601String());
        $user->save();

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error', 'session_revoked');
    }

    public function test_change_password_requires_the_correct_current_password(): void
    {
        [$user] = $this->makeTenantOwner();
        $token = $this->loginToken($user->email);

        $this->withToken($token)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'NotThePassword!1',
            'new_password' => 'BrandNewPass!9',
            'new_password_confirmation' => 'BrandNewPass!9',
        ])->assertStatus(422);
    }

    public function test_change_password_rotates_the_token(): void
    {
        [$user] = $this->makeTenantOwner();
        $token = $this->loginToken($user->email);

        $response = $this->withToken($token)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'SecretPass!123',
            'new_password' => 'BrandNewPass!1',
            'new_password_confirmation' => 'BrandNewPass!1',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $newToken = $response->json('data.token');
        $this->assertNotEmpty($newToken);

        // Old token is invalidated, new token authenticates.
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->withToken($newToken)->getJson('/api/v1/auth/me')->assertOk();

        // The new password is actually usable.
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'BrandNewPass!1',
        ])->assertOk();
    }
}
