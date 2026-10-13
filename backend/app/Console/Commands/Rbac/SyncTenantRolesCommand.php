<?php

namespace App\Console\Commands\Rbac;

use App\Models\Role;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\Rbac\TenantRoleMap;
use Database\Seeders\RbacSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * One-off/idempotent repair: re-point model_has_roles rows that were created
 * from the tenant-facing role vocabulary (e.g. "agent") to the canonical
 * Spatie role ("support_agent"). Without this, members invited before the
 * vocabulary was bridged keep a permission-less role and an empty sidebar.
 */
class SyncTenantRolesCommand extends Command
{
    protected $signature = 'rbac:sync-tenant-roles
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Remap tenant member Spatie roles to their canonical names and repair tenant scoping';

    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        setPermissionsTeamId(null);

        $dryRun = (bool) $this->option('dry-run');

        // Canonical roles (and their permissions) must exist before we remap.
        if (! $dryRun) {
            $this->callSilent('db:seed', [
                '--class' => RbacSeeder::class,
                '--force' => true,
            ]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $central = DB::connection('central');
        $rolesTable = config('permission.table_names.roles');
        $pivotTable = config('permission.table_names.model_has_roles');
        $roleKey = config('permission.column_names.role_pivot_key') ?: 'role_id';
        $teamKey = config('permission.column_names.team_foreign_key');
        $morphKey = config('permission.column_names.model_morph_key');
        $modelType = (new User)->getMorphClass();

        $roleIdByName = $central->table($rolesTable)->pluck('id', 'name');

        // Legacy aliases: role names that map onto a *different* canonical name
        // (e.g. "agent" -> "support_agent"). Canonical roles are left untouched.
        $aliasRoleIds = [];
        foreach ($roleIdByName as $name => $id) {
            $canonical = TenantRoleMap::toSpatie((string) $name);
            if ($canonical !== null && $canonical !== $name) {
                $aliasRoleIds[] = $id;
            }
        }

        $scanned = 0;
        $alreadyCorrect = 0;
        $rescoped = 0;
        $remapped = 0;
        $inserted = 0;
        $removedAliases = 0;
        $cleanedStrays = 0;
        $cleanedStrayUsers = [];
        $skipped = [];

        $pivots = TenantUser::query()
            ->whereNull('deleted_at')
            ->select('user_id', 'tenant_id', 'role')
            ->get();

        foreach ($pivots as $pivot) {
            $scanned++;
            $canonicalName = TenantRoleMap::toSpatie($pivot->role);

            if ($canonicalName === null || ! isset($roleIdByName[$canonicalName])) {
                $skipped[] = "user {$pivot->user_id} @ tenant {$pivot->tenant_id}: role '{$pivot->role}' has no canonical match";

                continue;
            }

            $canonicalId = $roleIdByName[$canonicalName];
            $tenantId = $pivot->tenant_id;

            // The real model_has_roles primary key is (role_id, model_id,
            // model_type) — tenant_id is NOT part of it — so a member can hold
            // a given role only once. Presence must be checked regardless of
            // the team actually stored on the row.
            $canonicalRow = $central->table($pivotTable)
                ->where($morphKey, $pivot->user_id)
                ->where('model_type', $modelType)
                ->where($roleKey, $canonicalId)
                ->first();

            $aliases = fn () => $central->table($pivotTable)
                ->where($morphKey, $pivot->user_id)
                ->where('model_type', $modelType)
                ->whereIn($roleKey, $aliasRoleIds ?: [0]);

            // Stray platform-scope rows (tenant_id NULL) still pointing at an
            // alias role are leftovers from the un-scoped admin assign path; a
            // tenant member's role must always be tenant-scoped.
            if (! isset($cleanedStrayUsers[$pivot->user_id]) && $aliasRoleIds !== []) {
                $cleanedStrayUsers[$pivot->user_id] = true;
                $stray = fn () => $central->table($pivotTable)
                    ->where($morphKey, $pivot->user_id)
                    ->where('model_type', $modelType)
                    ->whereNull($teamKey)
                    ->whereIn($roleKey, $aliasRoleIds);

                if ($dryRun) {
                    $cleanedStrays += (clone $stray())->count();
                } else {
                    $cleanedStrays += (clone $stray())->delete();
                }
            }

            if ($canonicalRow !== null) {
                if ($canonicalRow->{$teamKey} === null) {
                    $rescoped++;
                    if (! $dryRun) {
                        $central->table($pivotTable)
                            ->where($morphKey, $pivot->user_id)
                            ->where('model_type', $modelType)
                            ->where($roleKey, $canonicalId)
                            ->update([$teamKey => $tenantId]);
                    }
                } else {
                    $alreadyCorrect++;
                }

                if (! $dryRun) {
                    $removedAliases += (clone $aliases())->delete();
                }

                continue;
            }

            $aliasRows = (clone $aliases())->get();

            if ($aliasRows->isNotEmpty()) {
                $remapped++;
                if (! $dryRun) {
                    $primary = $aliasRows->first();
                    $central->table($pivotTable)
                        ->where('model_type', $modelType)
                        ->where($morphKey, $pivot->user_id)
                        ->where($roleKey, $primary->{$roleKey})
                        ->update([$roleKey => $canonicalId, $teamKey => $tenantId]);

                    // Any remaining alias rows for this member are redundant.
                    $removedAliases += (clone $aliases())->delete();
                }

                continue;
            }

            $inserted++;
            if (! $dryRun) {
                $central->table($pivotTable)->insert([
                    $roleKey => $canonicalId,
                    'model_type' => $modelType,
                    $morphKey => $pivot->user_id,
                    $teamKey => $tenantId,
                ]);
            }
        }

        // Drop alias roles that are no longer referenced anywhere.
        $deletedRoles = 0;
        foreach ($aliasRoleIds as $aliasId) {
            $stillUsed = $central->table($pivotTable)->where($roleKey, $aliasId)->exists();
            if (! $stillUsed) {
                $deletedRoles++;
                if (! $dryRun) {
                    $central->table($rolesTable)->where('id', $aliasId)->delete();
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        setPermissionsTeamId(null);

        $this->newLine();
        $this->info($dryRun ? 'DRY RUN — no changes written.' : 'Tenant role sync complete.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['tenant_user rows scanned', $scanned],
                ['already canonical', $alreadyCorrect],
                ['rescoped to tenant', $rescoped],
                ['remapped (alias -> canonical)', $remapped],
                ['inserted (missing canonical)', $inserted],
                ['alias rows removed', $removedAliases],
                ['stray null-tenant rows cleaned', $cleanedStrays],
                ['orphan alias roles deleted', $deletedRoles],
                ['skipped (no canonical match)', count($skipped)],
            ]
        );

        foreach ($skipped as $line) {
            $this->warn('SKIP '.$line);
        }

        return self::SUCCESS;
    }
}
