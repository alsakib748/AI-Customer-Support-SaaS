<?php

namespace Tests\Support;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shared fixtures for authentication / session tests that exercise the real
 * `jwt.auth` middleware against the central schema.
 */
trait InteractsWithAuth
{
    use InteractsWithCentralSchema;

    protected function bootAuthEnvironment(): void
    {
        // Production runs with the central DB as the default connection, so
        // mirror that for `unique`/`exists` validation and provider queries.
        config([
            'database.default' => 'central',
            'tenancy.database.central_connection' => 'central',
            'cache.default' => 'array',
        ]);

        DB::purge('central');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->createCentralSchema();
        $this->createDefaultPlan();

        (new RbacSeeder)->run();
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    protected function makeTenantOwner(array $userOverrides = [], string $role = 'owner', array $tenantOverrides = []): array
    {
        $tenant = Tenant::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'name' => 'Acme '.uniqid(),
            'slug' => 'acme-'.uniqid(),
            'status' => Tenant::STATUS_ACTIVE,
            'provisioned_at' => now(),
        ], $tenantOverrides));

        $user = User::query()->create(array_merge([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'user'.uniqid().'@example.test',
            'password' => 'SecretPass!123',
            'is_active' => true,
            'current_tenant_id' => $tenant->id,
        ], $userOverrides));

        TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
            'accepted_at' => now(),
        ]);

        setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->assignRole(Role::findByName($role, 'api'));
        $user->unsetRelation('roles');
        setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [$user->fresh(), $tenant];
    }

    protected function loginToken(string $email, string $password = 'SecretPass!123'): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $response->assertOk();

        return $response->json('data.token');
    }
}
