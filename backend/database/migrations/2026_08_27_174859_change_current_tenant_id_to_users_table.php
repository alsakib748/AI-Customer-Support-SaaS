<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['current_tenant_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('current_tenant_id')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('current_tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['current_tenant_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('current_tenant_id')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('current_tenant_id');
        });
    }
};