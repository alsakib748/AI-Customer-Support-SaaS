<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\JWTAuthMiddleware;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\InteractsWithAuth;
use Tests\TestCase;

/**
 * Platform Super Admin override to mark a user's email as verified.
 */
class UserVerifyEmailTest extends TestCase
{
    use InteractsWithAuth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootAuthEnvironment();
    }

    protected function makeSuperAdmin(): User
    {
        setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::query()->create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'super'.uniqid().'@example.test',
            'password' => 'SecretPass!123',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $admin->assignRole(Role::findByName('super_admin', 'api'));

        return $admin->fresh();
    }

    public function test_super_admin_can_verify_a_users_email(): void
    {
        $admin = $this->makeSuperAdmin();
        [$target] = $this->makeTenantOwner(['email_verified_at' => null]);

        $this->withoutMiddleware(JWTAuthMiddleware::class);
        $this->actingAs($admin, 'api');

        $this->postJson("/api/v1/admin/users/{$target->id}/verify-email")
            ->assertOk()
            ->assertJsonPath('data.email_verified', true);

        $this->assertNotNull($target->fresh()->email_verified_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.email_verified',
            'resource_type' => 'user',
            'resource_id' => $target->id,
        ], 'central');
    }

    public function test_verifying_an_already_verified_user_is_rejected(): void
    {
        $admin = $this->makeSuperAdmin();
        [$target] = $this->makeTenantOwner();

        $this->withoutMiddleware(JWTAuthMiddleware::class);
        $this->actingAs($admin, 'api');

        $this->postJson("/api/v1/admin/users/{$target->id}/verify-email")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_super_admin_accounts_are_protected(): void
    {
        $admin = $this->makeSuperAdmin();
        $other = $this->makeSuperAdmin();

        $this->withoutMiddleware(JWTAuthMiddleware::class);
        $this->actingAs($admin, 'api');

        $this->postJson("/api/v1/admin/users/{$other->id}/verify-email")
            ->assertStatus(403);
    }

    public function test_non_super_admin_cannot_reach_the_endpoint(): void
    {
        [$actor] = $this->makeTenantOwner();
        [$target] = $this->makeTenantOwner(['email_verified_at' => null]);

        $this->withoutMiddleware(JWTAuthMiddleware::class);
        $this->actingAs($actor, 'api');

        $this->postJson("/api/v1/admin/users/{$target->id}/verify-email")
            ->assertStatus(403);

        $this->assertNull($target->fresh()->email_verified_at);
    }
}
