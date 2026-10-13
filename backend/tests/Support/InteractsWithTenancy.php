<?php

namespace Tests\Support;

use App\Http\Middleware\JWTAuthMiddleware;
use App\Http\Middleware\TenantAware;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reusable, explicit tenant test harness for a database-per-tenant app.
 *
 * Tests must:
 *   - build the central schema (users, tenants, RBAC, billing slices),
 *   - build each tenant schema from the real tenant migrations against an
 *     isolated SQLite database,
 *   - establish an explicit tenant context (connection + container binding +
 *     Spatie team + Stancl tenancy) before exercising tenant-owned code.
 *
 * The first tenant database built in a process becomes a template; later
 * databases are fast file copies of it.
 *
 * Stancl's database bootstrapper is disabled in tests: the tenant connection
 * is a static file database, and letting the bootstrapper rewrite the
 * connection would point it at a non-existent path and silently drop the
 * migrated schema.
 */
trait InteractsWithTenancy
{
    protected ?Tenant $currentTenantContext = null;

    /** @var array<string, string> tenant id => sqlite file path */
    protected array $tenantDatabaseFiles = [];

    protected static ?string $tenantSchemaTemplate = null;

    protected function useIsolatedTenantDatabase(): void
    {
        config([
            'database.default' => 'tenant',
            'database.connections.tenant' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            // Stancl resolves the "central" connection from this config key
            // (env('DB_CONNECTION') is 'sqlite' under phpunit), so point it at
            // the schema this suite actually builds.
            'tenancy.database.central_connection' => 'central',
            'tenancy.bootstrappers' => [],
        ]);

        DB::purge('tenant');
    }

    /**
     * Path to the migrated tenant schema template, building it once per process.
     */
    protected function tenantSchemaTemplate(): string
    {
        if (self::$tenantSchemaTemplate && is_file(self::$tenantSchemaTemplate)) {
            return self::$tenantSchemaTemplate;
        }

        $path = $this->newSqliteFile('tenant_template');
        $this->pointTenantConnectionAt($path);

        Artisan::call('migrate', [
            '--path' => database_path('migrations/tenant'),
            '--realpath' => true,
            '--database' => 'tenant',
            '--force' => true,
        ]);

        self::$tenantSchemaTemplate = $path;

        return $path;
    }

    /**
     * Allocate an isolated tenant database (a copy of the migrated template).
     */
    protected function prepareTenantDatabase(Tenant $tenant): string
    {
        $path = $this->newSqliteFile('tenant_'.$tenant->id);

        copy($this->tenantSchemaTemplate(), $path);

        $this->tenantDatabaseFiles[$tenant->id] = $path;

        return $path;
    }

    protected function pointTenantConnectionAt(string $path): void
    {
        config(['database.connections.tenant.database' => $path]);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    protected function newSqliteFile(string $label): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_]/', '_', $label);
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'opencode_'.$safe.'_'.Str::random(8).'.sqlite';

        if (! is_file($path)) {
            touch($path);
        }

        return $path;
    }

    protected function seedRbac(): void
    {
        (new RbacSeeder)->run();
    }

    protected function newPlan(array $overrides = []): Plan
    {
        return Plan::query()->create(array_merge([
            'name' => 'Starter',
            'slug' => 'starter-'.uniqid(),
            'description' => 'Starter plan',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'currency' => 'USD',
            'trial_days' => 0,
            'is_active' => true,
            'is_default' => true,
            'is_public' => true,
            'features' => [],
            'limits' => [],
        ], $overrides));
    }

    protected function newTenant(array $overrides = []): Tenant
    {
        return Tenant::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'name' => 'Acme Corp '.uniqid(),
            'slug' => 'acme-'.uniqid(),
            'status' => Tenant::STATUS_ACTIVE,
            'provisioned_at' => now(),
        ], $overrides));
    }

    protected function newTenantUser(Tenant $tenant, string $role = 'owner', array $overrides = []): User
    {
        $user = User::query()->create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'user'.uniqid().'@example.test',
            'password' => 'SecretPass!123',
            'is_active' => true,
            'email_verified_at' => now(),
            'current_tenant_id' => $tenant->id,
        ], $overrides));

        TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
            'accepted_at' => now(),
        ]);

        $this->assignTenantRole($tenant, $user, $role);

        return $user->fresh();
    }

    protected function assignTenantRole(Tenant $tenant, User $user, string $role): void
    {
        setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user->assignRole(Role::findOrCreate($role, 'api'));

        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');
    }

    protected function newSubscription(Tenant $tenant, ?Plan $plan = null, array $overrides = []): Subscription
    {
        $plan ??= $this->newPlan();

        return Subscription::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => true,
            'next_billing_at' => now()->addMonth(),
        ], $overrides));
    }

    /**
     * Convenience: a tenant with its own database, an owner and an active
     * subscription.
     *
     * @return array{tenant: Tenant, owner: User, subscription: Subscription}
     */
    protected function tenantWithOwner(array $tenantOverrides = []): array
    {
        $tenant = $this->newTenant($tenantOverrides);
        $owner = $this->newTenantUser($tenant, 'owner');
        $subscription = $this->newSubscription($tenant);

        $this->prepareTenantDatabase($tenant);

        return ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription];
    }

    /**
     * Act as the tenant for the current test: switch the tenant connection to
     * the tenant's database, initialize tenancy, bind the tenant, set the
     * Spatie team and authenticate the user.
     */
    protected function establishTenantContext(Tenant $tenant, ?User $user = null): void
    {
        if (! isset($this->tenantDatabaseFiles[$tenant->id])) {
            $this->prepareTenantDatabase($tenant);
        }

        $this->pointTenantConnectionAt($this->tenantDatabaseFiles[$tenant->id]);

        $this->currentTenantContext = $tenant;

        app()->instance('current_tenant', $tenant);
        request()->attributes->set('current_tenant', $tenant);

        tenancy()->initialize($tenant);

        setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($user) {
            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');
            $this->actingAs($user, 'api');
        }
    }

    protected function tearDownTenantContext(): void
    {
        if ($this->currentTenantContext) {
            tenancy()->end();
            $this->currentTenantContext = null;
        }

        setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app()->forgetInstance('current_tenant');

        foreach ($this->tenantDatabaseFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->tenantDatabaseFiles = [];
    }

    /**
     * Middleware to skip for tenant HTTP tests. JWT requires a real bearer
     * token; TenantAware re-resolves the tenant (and is tested separately).
     * RBAC, membership and subscription middleware intentionally stay enabled.
     *
     * @return array<class-string>
     */
    protected function tenantHttpMiddlewareToBypass(): array
    {
        return [
            JWTAuthMiddleware::class,
            TenantAware::class,
        ];
    }
}
