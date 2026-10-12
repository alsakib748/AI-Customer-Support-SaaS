<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithAuth;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use InteractsWithAuth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAuthEnvironment();
    }

    public function test_login_returns_token_and_owner_scope(): void
    {
        [$user, $tenant] = $this->makeTenantOwner();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'SecretPass!123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'owner')
            ->assertJsonPath('data.scope', 'tenant')
            ->assertJsonPath('data.tenant.id', $tenant->id)
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email'], 'permissions', 'roles']]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertContains('customers.view', $response->json('data.permissions'));
    }

    public function test_login_does_not_issue_a_token_for_a_wrong_password(): void
    {
        [$user] = $this->makeTenantOwner();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPassword!1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['password']]);

        $this->assertNull($response->json('data.token'));
    }

    public function test_login_does_not_reveal_validity_for_an_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.test',
            'password' => 'SecretPass!123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['email']]);

        $this->assertNull($response->json('data.token'));
    }

    public function test_deactivated_user_cannot_login(): void
    {
        [$user] = $this->makeTenantOwner(['is_active' => false]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'SecretPass!123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['account']]);

        $this->assertNull($response->json('data.token'));
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email', 'password']]);
    }

    public function test_login_records_last_login_metadata(): void
    {
        [$user] = $this->makeTenantOwner();

        $this->assertNull($user->last_login_at);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'SecretPass!123',
        ])->assertOk();

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull($user->last_login_ip);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error', 'token_absent');
    }

    public function test_me_returns_the_authenticated_context(): void
    {
        [$user, $tenant] = $this->makeTenantOwner();
        $token = $this->loginToken($user->email);

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'owner')
            ->assertJsonPath('data.scope', 'tenant')
            ->assertJsonPath('data.tenant.id', $tenant->id);
    }

    public function test_token_issued_to_a_deactivated_user_is_rejected(): void
    {
        [$user] = $this->makeTenantOwner();
        $token = $this->loginToken($user->email);

        DB::connection('central')->table('users')->where('id', $user->id)->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(403)
            ->assertJsonPath('error', 'account_inactive');
    }

    public function test_validate_token_endpoint_reports_valid_and_invalid_tokens(): void
    {
        [$user] = $this->makeTenantOwner();
        $token = $this->loginToken($user->email);

        $this->postJson('/api/v1/auth/validate-token')
            ->assertStatus(400)
            ->assertJsonPath('valid', false);

        $this->withToken($token)->postJson('/api/v1/auth/validate-token')
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.user.email', $user->email);

        $this->withToken('not-a-real-token')->postJson('/api/v1/auth/validate-token')
            ->assertStatus(401)
            ->assertJsonPath('valid', false);
    }

    public function test_login_never_leaks_the_password_hash(): void
    {
        [$user] = $this->makeTenantOwner();
        $token = $this->loginToken($user->email);

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertOk();
        $this->assertStringNotContainsString($user->password, $response->getContent());
        $this->assertArrayNotHasKey('password', $response->json('data.user'));
    }
}
