<?php

namespace Tests\Feature\Audit;

use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\JWTAuthMiddleware;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Notifications\InteractsWithPlatformNotifications;
use Tests\TestCase;

class UserPermissionSyncTest extends TestCase
{
    use InteractsWithPlatformNotifications;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'central']);
        config(['tenancy.database.central_connection' => 'central']);
        config(['cache.default' => 'array']);
        DB::purge('central');

        $this->createCentralNotificationSchema();
        $this->ensureSuperAdminRole();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->withoutMiddleware(JWTAuthMiddleware::class);
    }

    protected function as(User $user): static
    {
        $this->actingAs($user, 'api');

        return $this;
    }

    protected function makePermission(string $name): Permission
    {
        return Permission::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'api',
        ]);
    }

    public function test_super_admin_can_sync_direct_user_permissions(): void
    {
        $admin = $this->makeSuperAdmin();
        $target = $this->makeUser();
        $this->makePermission('customers.view');

        $response = $this->as($admin)->putJson(
            "/api/v1/admin/rbac/users/{$target->id}/permissions",
            ['permissions' => ['customers.view']]
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.permissions', ['customers.view']);

        $this->assertTrue($target->fresh()->hasDirectPermission('customers.view'));
    }

    public function test_unknown_permission_is_rejected(): void
    {
        $admin = $this->makeSuperAdmin();
        $target = $this->makeUser();

        $this->as($admin)->putJson(
            "/api/v1/admin/rbac/users/{$target->id}/permissions",
            ['permissions' => ['does.not.exist']]
        )->assertStatus(422);
    }

    public function test_non_super_admin_cannot_reach_the_endpoint(): void
    {
        $user = $this->makeUser();
        $target = $this->makeUser();
        $this->makePermission('customers.view');

        $this->as($user)->putJson(
            "/api/v1/admin/rbac/users/{$target->id}/permissions",
            ['permissions' => ['customers.view']]
        )->assertStatus(403);
    }

    public function test_admin_cannot_grant_a_permission_they_do_not_hold(): void
    {
        $this->makePermission('customers.view');
        $this->makePermission('billing.manage');

        $actor = $this->makeUser();
        $actor->givePermissionTo('customers.view');

        $target = $this->makeUser();

        $this->withoutMiddleware([EnsureSuperAdmin::class]);

        $this->as($actor)->putJson(
            "/api/v1/admin/rbac/users/{$target->id}/permissions",
            ['permissions' => ['billing.manage']]
        )->assertStatus(422);

        $this->assertFalse($target->fresh()->hasDirectPermission('billing.manage'));
    }

    public function test_admin_can_grant_a_permission_they_hold(): void
    {
        $this->makePermission('customers.view');

        $actor = $this->makeUser();
        $actor->givePermissionTo('customers.view');

        $target = $this->makeUser();

        $this->withoutMiddleware([EnsureSuperAdmin::class]);

        $this->as($actor)->putJson(
            "/api/v1/admin/rbac/users/{$target->id}/permissions",
            ['permissions' => ['customers.view']]
        )->assertOk();

        $this->assertTrue($target->fresh()->hasDirectPermission('customers.view'));
    }
}
