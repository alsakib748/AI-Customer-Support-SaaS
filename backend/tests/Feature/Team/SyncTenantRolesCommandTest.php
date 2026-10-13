<?php

namespace Tests\Feature\Team;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\InteractsWithCentralSchema;
use Tests\TestCase;

class SyncTenantRolesCommandTest extends TestCase
{
    use InteractsWithCentralSchema;

    protected string $tenantId;

    protected User $agentMember;

    protected User $viewerMember;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'central']);
        config(['tenancy.database.central_connection' => 'central']);
        config(['cache.default' => 'array']);
        DB::purge('central');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->createCentralSchema();
        $this->seed(RbacSeeder::class);

        setPermissionsTeamId(null);

        $this->tenantId = (string) Str::uuid();

        Tenant::create([
            'id' => $this->tenantId,
            'name' => 'Acme Corp',
            'slug' => 'acme',
            'status' => 'active',
        ]);

        $this->agentMember = $this->createMember('legacy-agent@example.test', 'agent');
        $this->viewerMember = $this->createMember('viewer@example.test', 'viewer');
    }

    protected function createMember(string $email, string $pivotRole): User
    {
        $user = User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'password' => 'SecretPass!123',
            'is_active' => true,
            'email_verified_at' => now(),
            'current_tenant_id' => $this->tenantId,
        ]);

        TenantUser::query()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $user->id,
            'role' => $pivotRole,
            'accepted_at' => now(),
        ]);

        return $user->fresh();
    }

    protected function assignRaw(User $user, string $roleName, ?string $tenantId): int
    {
        $role = Role::findOrCreate($roleName, 'api');

        DB::connection('central')->table($this->pivotTable())->insert([
            $this->roleKey() => $role->id,
            'model_type' => (new User)->getMorphClass(),
            $this->morphKey() => $user->id,
            $this->teamKey() => $tenantId,
        ]);

        return $role->id;
    }

    protected function roleId(string $name): int
    {
        return (int) DB::connection('central')
            ->table(config('permission.table_names.roles'))
            ->where('name', $name)
            ->value('id');
    }

    protected function rowCount(int $userId, int $roleId, ?string $tenantId): int
    {
        $query = DB::connection('central')->table($this->pivotTable())
            ->where($this->morphKey(), $userId)
            ->where($this->roleKey(), $roleId);

        return $tenantId === null
            ? $query->whereNull($this->teamKey())->count()
            : $query->where($this->teamKey(), $tenantId)->count();
    }

    protected function pivotTable(): string
    {
        return config('permission.table_names.model_has_roles');
    }

    protected function roleKey(): string
    {
        return config('permission.column_names.role_pivot_key') ?: 'role_id';
    }

    protected function morphKey(): string
    {
        return config('permission.column_names.model_morph_key');
    }

    protected function teamKey(): string
    {
        return config('permission.column_names.team_foreign_key');
    }

    public function test_it_remaps_legacy_agent_rows_to_support_agent(): void
    {
        $this->assignRaw($this->agentMember, 'agent', $this->tenantId);

        Artisan::call('rbac:sync-tenant-roles');

        $this->assertSame(1, $this->rowCount($this->agentMember->id, $this->roleId('support_agent'), $this->tenantId));
        $this->assertSame(0, $this->rowCount($this->agentMember->id, $this->roleId('support_agent'), null));
        $this->assertSame(0, Role::where('name', 'agent')->count(), 'the legacy alias role is deleted once unreferenced');
    }

    public function test_it_is_idempotent(): void
    {
        $this->assignRaw($this->agentMember, 'agent', $this->tenantId);

        Artisan::call('rbac:sync-tenant-roles');
        Artisan::call('rbac:sync-tenant-roles');

        $this->assertSame(1, $this->rowCount($this->agentMember->id, $this->roleId('support_agent'), $this->tenantId));
    }

    public function test_it_leaves_already_canonical_rows_untouched(): void
    {
        $this->assignRaw($this->viewerMember, 'viewer', $this->tenantId);

        Artisan::call('rbac:sync-tenant-roles');

        $this->assertSame(1, $this->rowCount($this->viewerMember->id, $this->roleId('viewer'), $this->tenantId));
        $this->assertSame(0, $this->rowCount($this->viewerMember->id, $this->roleId('support_agent'), $this->tenantId));
    }

    public function test_it_recreates_a_missing_canonical_row_from_the_pivot(): void
    {
        $this->assertSame(0, $this->rowCount($this->viewerMember->id, $this->roleId('viewer'), $this->tenantId));

        Artisan::call('rbac:sync-tenant-roles');

        $this->assertSame(1, $this->rowCount($this->viewerMember->id, $this->roleId('viewer'), $this->tenantId));
    }

    public function test_it_repairs_a_platform_scoped_alias_row(): void
    {
        $this->assignRaw($this->agentMember, 'agent', null);

        Artisan::call('rbac:sync-tenant-roles');

        $this->assertSame(1, $this->rowCount($this->agentMember->id, $this->roleId('support_agent'), $this->tenantId));
        $this->assertSame(0, $this->rowCount($this->agentMember->id, $this->roleId('support_agent'), null));
    }

    public function test_it_rescopes_a_platform_scoped_canonical_row(): void
    {
        $this->assignRaw($this->viewerMember, 'viewer', null);

        Artisan::call('rbac:sync-tenant-roles');

        $this->assertSame(1, $this->rowCount($this->viewerMember->id, $this->roleId('viewer'), $this->tenantId));
        $this->assertSame(0, $this->rowCount($this->viewerMember->id, $this->roleId('viewer'), null));
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->assignRaw($this->agentMember, 'agent', $this->tenantId);

        Artisan::call('rbac:sync-tenant-roles', ['--dry-run' => true]);

        $this->assertSame(1, $this->rowCount($this->agentMember->id, $this->roleId('agent'), $this->tenantId));
        $this->assertSame(0, $this->rowCount($this->agentMember->id, $this->roleId('support_agent'), $this->tenantId));
        $this->assertSame(1, Role::where('name', 'agent')->count(), 'dry-run must not delete the alias role');
    }
}
