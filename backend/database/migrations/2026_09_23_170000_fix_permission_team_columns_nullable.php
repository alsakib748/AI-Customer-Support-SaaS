<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function tables(): array
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $morphKey = $columnNames['model_morph_key'] ?? 'model_id';
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        return [
            [
                'table' => $tableNames['model_has_roles'],
                'pivotKey' => $pivotRole,
                'primary' => 'model_has_roles_role_model_type_primary',
            ],
            [
                'table' => $tableNames['model_has_permissions'],
                'pivotKey' => $pivotPermission,
                'primary' => 'model_has_permissions_permission_model_type_primary',
            ],
        ];
    }

    /**
     * Global (platform-scope) roles/permissions like "super_admin" are stored
     * with a null team foreign key. PostgreSQL refuses to keep a nullable column
     * inside a primary key, so the team foreign key is excluded from the pivot
     * primary key and left nullable.
     */
    public function up(): void
    {
        if (! config('permission.teams')) {
            return;
        }

        foreach ($this->tables() as $info) {
            if (! Schema::hasColumn($info['table'], config('permission.column_names')['team_foreign_key'])) {
                continue;
            }

            Schema::table($info['table'], function (Blueprint $table) use ($info) {
                $table->dropPrimary($info['primary']);
                $table->unsignedBigInteger(config('permission.column_names')['team_foreign_key'])->nullable()->default(null)->change();
                $table->primary(
                    [$info['pivotKey'], config('permission.column_names')['model_morph_key'] ?? 'model_id', 'model_type'],
                    $info['primary']
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! config('permission.teams')) {
            return;
        }

        foreach ($this->tables() as $info) {
            $teamKey = config('permission.column_names')['team_foreign_key'];
            $morphKey = config('permission.column_names')['model_morph_key'] ?? 'model_id';

            if (! Schema::hasColumn($info['table'], $teamKey)) {
                continue;
            }

            DB::table($info['table'])->whereNull($teamKey)->update([$teamKey => 1]);

            Schema::table($info['table'], function (Blueprint $table) use ($info, $teamKey, $morphKey) {
                $table->dropPrimary($info['primary']);
                $table->unsignedBigInteger($teamKey)->default('1')->change();
                $table->primary([$teamKey, $info['pivotKey'], $morphKey, 'model_type'], $info['primary']);
            });
        }
    }
};