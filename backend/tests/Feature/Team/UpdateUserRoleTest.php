<?php

namespace Tests\Feature\Team;

use App\Http\Controllers\Api\V1\TenantController;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\InteractsWithCentralSchema;
use Tests\TestCase;

class UpdateUserRoleTest extends TestCase
{
    use InteractsWithCentralSchema;

    protected string $tenantId;

    protected User $owner;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'central']);
        config(['tenancy.database.central_connection' => 'central']);
        config(['cache.default' => 'array']);
        DB::purge('central');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->createCentralSchema();

        $this->tenantId = (string) Str::uuid();

        Tenant::create([
            'id' => $this->tenantId,
            'name' => 'Acme Corp',
            'slug' => 'acme',
            'status' => 'active',
        ]);

        $this->owner = $this->createUser('owner@example.test');
        $this->member = $this->createUser('member@example.test');

        TenantUser::create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->owner->id,
            'role' => 'owner',
            'accepted_at' => now(),
        ]);

        TenantUser::create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->member->id,
            'role' => 'agent',
            'accepted_at' => now(),
        ]);

        setPermissionsTeamId($this->tenantId);

        $this->owner->assignRole(Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'api']));
        $this->member->assignRole(Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'api']));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function createUser(string $email): User
    {
        return User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'password' => 'SecretPass!123',
            'is_active' => true,
        ]);
    }

    public function test_route_is_wired_to_the_controller_action(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())->first(
            fn ($route) => $route->uri() === 'api/v1/tenants/{tenantId}/users/{userId}/role'
                && in_array('PUT', $route->methods(), true)
        );

        $this->assertNotNull($route, 'the PUT tenant role route must be registered');
        $this->assertSame(
            TenantController::class.'@updateUserRole',
            $route->getActionName(),
            'the route must resolve to TenantController::updateUserRole'
        );
        $this->assertContains('permission:team.assign_role', $route->middleware());
    }

    public function test_service_updates_role_and_reports_the_previous_role(): void
    {
        $service = app(TenantService::class);

        $result = $service->updateUserRole($this->tenantId, $this->member->id, 'manager');

        $this->assertSame($this->member->id, $result['user_id']);
        $this->assertSame('agent', $result['old_role'], 'old_role must be the role before the change');
        $this->assertSame('manager', $result['new_role']);

        $this->assertSame(
            'manager',
            TenantUser::where('tenant_id', $this->tenantId)->where('user_id', $this->member->id)->value('role')
        );

        setPermissionsTeamId($this->tenantId);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $member = $this->member->fresh();
        $this->assertTrue($member->hasRole('manager'), 'the Spatie role must be synced to the new role');
        $this->assertFalse($member->hasRole('agent'), 'the previous Spatie role must be removed');
    }

    public function test_service_refuses_to_demote_the_last_owner(): void
    {
        $service = app(TenantService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot change the role of the last owner');

        $service->updateUserRole($this->tenantId, $this->owner->id, 'admin');
    }

    public function test_service_reports_when_user_is_not_in_the_tenant(): void
    {
        $service = app(TenantService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User not found in this tenant');

        $service->updateUserRole($this->tenantId, 999999, 'admin');
    }

    public function test_controller_rejects_an_unknown_role(): void
    {
        $controller = app(TenantController::class);

        $request = Request::create('/', 'PUT', ['role' => 'super_admin']);

        $response = $controller->updateUserRole($request, $this->tenantId, $this->member->id);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(false, $response->getData(true)['success']);
        $this->assertArrayHasKey('role', $response->getData(true)['errors']);
    }

    public function test_controller_updates_the_role_and_audits_it(): void
    {
        $controller = app(TenantController::class);

        $request = Request::create('/', 'PUT', ['role' => 'viewer']);

        $response = $controller->updateUserRole($request, $this->tenantId, $this->member->id);

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertSame('agent', $payload['data']['old_role']);
        $this->assertSame('viewer', $payload['data']['new_role']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user_role_updated',
            'resource_type' => 'user',
            'resource_id' => $this->member->id,
        ], 'central');
    }

    public function test_controller_returns_a_client_error_when_the_user_is_missing(): void
    {
        $controller = app(TenantController::class);

        $request = Request::create('/', 'PUT', ['role' => 'admin']);

        $response = $controller->updateUserRole($request, $this->tenantId, 999999);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(false, $response->getData(true)['success']);
    }

    protected function tearDown(): void
    {
        setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }
}
