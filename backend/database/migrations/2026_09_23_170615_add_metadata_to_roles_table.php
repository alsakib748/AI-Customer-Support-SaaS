<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        
        Schema::connection('central')->table('roles', function (Blueprint $table) {
            if (!Schema::connection('central')->hasColumn('roles', 'description')) {
                $table->string('description')->nullable()->after('name');
            }
            if (!Schema::connection('central')->hasColumn('roles', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('description');
            }
            if (!Schema::connection('central')->hasColumn('roles', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_system');
            }
        });

        Schema::connection('central')->table('permissions', function (Blueprint $table) {
            if (!Schema::connection('central')->hasColumn('permissions', 'module')) {
                $table->string('module', 50)->nullable()->after('name');
            }
            if (!Schema::connection('central')->hasColumn('permissions', 'label')) {
                $table->string('label')->nullable()->after('module');
            }
            if (!Schema::connection('central')->hasColumn('permissions', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('label');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->table('roles', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_system', 'sort_order']);
        });

        Schema::connection('central')->table('permissions', function (Blueprint $table) {
            $table->dropColumn(['module', 'label', 'is_system']);
        });
    }
};