<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailVerificationMail;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\SystemSettings\SystemSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\Support\InteractsWithCentralSchema;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use InteractsWithCentralSchema;

    protected function setUp(): void
    {
        parent::setUp();

        // Production runs with the central DB as the default connection
        // (DB_CONNECTION=pgsql). Mirror that so the `unique`/`exists` validation
        // rules resolve against the same table the models write to.
        config(['database.default' => 'central']);
        config(['tenancy.database.central_connection' => 'central']);
        config(['cache.default' => 'array']);
        DB::purge('central');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->createCentralSchema();
        $this->createDefaultPlan();
    }

    public function test_registration_creates_user_tenant_and_owner_role(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->registrationPayload());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'role', 'scope', 'email_verification_required',
                    'user' => ['id', 'email', 'full_name', 'roles'],
                    'tenant' => ['id', 'name', 'subdomain', 'logo_url', 'status'],
                ],
            ])
            ->assertJsonPath('data.role', 'owner')
            ->assertJsonPath('data.scope', 'tenant')
            ->assertJsonPath('data.email_verification_required', true)
            ->assertJsonMissingPath('data.token');

        $user = User::query()->where('email', $response->json('data.user.email'))->firstOrFail();

        $tenantId = $response->json('data.tenant.id');

        // Roles are team-scoped, so evaluate hasRole() inside the tenant team.
        setPermissionsTeamId($tenantId);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->unsetRelation('roles');

        $this->assertTrue($user->hasRole('owner'), 'user should hold the owner role');
        $this->assertTrue($user->hasRole('owner', 'api'), 'owner role should use the api guard');

        $this->assertNotSame('active', $tenantId);
        $this->assertSame(1, DB::connection('central')->table('tenants')->where('id', $tenantId)->count());
        $this->assertSame($tenantId, $user->current_tenant_id);

        $this->assertSame(
            1,
            DB::connection('central')->table('tenant_user')->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('role', 'owner')->count(),
            'the registering user must be the tenant owner'
        );

        $this->assertSame(1, DB::connection('central')->table('domains')->where('tenant_id', $tenantId)->count());
    }

    public function test_registration_emails_a_verification_link_and_blocks_login_until_verified(): void
    {
        Mail::fake();
        config(['app.frontend_url' => 'https://app.example.test']);

        $response = $this->postJson('/api/v1/auth/register', $this->registrationPayload([
            'email' => 'verify-me@example.test',
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.email_verification_required', true);

        $user = User::query()->where('email', 'verify-me@example.test')->firstOrFail();
        $this->assertNull($user->email_verified_at, 'a new registration must start unverified');

        Mail::assertQueued(EmailVerificationMail::class, function (EmailVerificationMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && str_contains($mail->verifyUrl, "/verify-email/{$user->id}/");
        });

        // Correct credentials, but the account is not verified yet.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'verify-me@example.test',
            'password' => 'SecretPass!123',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'email_not_verified');
    }

    public function test_registration_assigns_default_plan_and_trial_status(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->registrationPayload());

        $response->assertStatus(201);

        $tenantId = $response->json('data.tenant.id');
        $tenant = Tenant::query()->findOrFail($tenantId);

        $this->assertSame('trial', $tenant->status, 'default plan has trial days, so tenant starts in trial');

        $subscription = DB::connection('central')->table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->first();

        $this->assertNotNull($subscription, 'a default subscription must be auto-provisioned');
        $this->assertSame('trialing', $subscription->status);
    }

    public function test_company_name_falls_back_to_user_workspace_when_omitted(): void
    {
        $payload = $this->registrationPayload(['company_name' => null]);

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.tenant.name', "Jane Doe's Workspace");
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $email = 'dupe@example.test';
        User::query()->create([
            'first_name' => 'Old',
            'last_name'  => 'User',
            'email'      => $email,
            'password'   => 'SecretPass!123',
            'is_active'  => true,
        ]);

        $response = $this->postJson('/api/v1/auth/register', $this->registrationPayload(['email' => $email]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['email']]);

        $this->assertSame(1, DB::connection('central')->table('users')->where('email', $email)->count());
    }

    public function test_registration_rejects_weak_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->registrationPayload(['password' => 'short', 'password_confirmation' => 'short']));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['password']]);
    }

    public function test_registration_rejects_missing_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email'    => 'partial@example.test',
            'password' => 'SecretPass!123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['first_name', 'last_name']]);
    }

    public function test_registration_cleans_up_user_when_tenant_provisioning_fails(): void
    {
        // Force Tenant::create() to fail after the user row was created, so the
        // compensating cleanup in AuthService::register must remove the user.
        Tenant::saving(function () {
            throw new \RuntimeException('simulated provisioning failure');
        });

        $email = 'fail@example.test';

        $response = $this->postJson('/api/v1/auth/register', $this->registrationPayload(['email' => $email]));

        $response->assertStatus(500)
            ->assertJsonPath('success', false);

        $this->assertSame(0, DB::connection('central')->table('users')->where('email', $email)->whereNull('deleted_at')->count(), 'orphaned user must be cleaned up');
        $this->assertSame(0, DB::connection('central')->table('tenants')->count());
    }

    public function test_registration_is_blocked_during_maintenance(): void
    {
        $maintenance = $this->createMock(SystemSettingsService::class);
        $maintenance->method('isMaintenanceEnabled')->willReturn(true);
        $maintenance->method('get')->willReturn(false);
        $this->app->instance(SystemSettingsService::class, $maintenance);

        $response = $this->postJson('/api/v1/auth/register', $this->registrationPayload());

        $response->assertStatus(503)
            ->assertJsonPath('maintenance', true);

        $this->assertSame(0, DB::connection('central')->table('users')->count());
    }

    public function test_tenant_creation_has_no_side_effects_without_provisioning(): void
    {
        $this->assertNull(
            $this->app->make('events')->getListeners(TenantCreated::class)[0] ?? null,
            'tests must disable the Stancl tenant provisioning pipeline'
        );
    }
}