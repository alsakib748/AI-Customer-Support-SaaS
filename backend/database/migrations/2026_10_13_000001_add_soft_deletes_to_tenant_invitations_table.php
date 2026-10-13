<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * The TenantInvitation model uses SoftDeletes and the central test schema
     * defines deleted_at, but the original create migration omitted it. Add it
     * for existing and fresh installations.
     */
    public function up(): void
    {
        if (Schema::connection('central')->hasColumn('tenant_invitations', 'deleted_at')) {
            return;
        }

        Schema::connection('central')->table('tenant_invitations', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (! Schema::connection('central')->hasColumn('tenant_invitations', 'deleted_at')) {
            return;
        }

        Schema::connection('central')->table('tenant_invitations', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
