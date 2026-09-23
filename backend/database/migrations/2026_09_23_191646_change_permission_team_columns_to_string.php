<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    private function tables(): array
    {
        $tableNames  = config('permission.table_names');
        $teamKey     = config('permission.column_names.team_foreign_key');

        return array_filter([
            $tableNames['roles'],
            $tableNames['model_has_roles'],
            $tableNames['model_has_permissions'],
        ], fn ($t) => $t && Schema::connection('central')->hasColumn($t, $teamKey));
    }

    /**
     * Tenants use UUID ids, but Spatie's team_foreign_key columns were
     * created as bigint. Widen them to string so UUID team ids persist
     * correctly in roles/model_has_roles/model_has_permissions.
     */
    public function up(): void
    {
        $teamKey = config('permission.column_names.team_foreign_key');

        foreach ($this->tables() as $table) {
            Schema::connection('central')->table($table, function (Blueprint $table) use ($teamKey) {
                $table->string($teamKey, 36)->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $teamKey = config('permission.column_names.team_foreign_key');

        foreach ($this->tables() as $table) {
            Schema::connection('central')->table($table, function (Blueprint $table) use ($teamKey) {
                $table->unsignedBigInteger($teamKey)->nullable()->change();
            });
        }
    }
};