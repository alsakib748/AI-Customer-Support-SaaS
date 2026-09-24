<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('trial_ends_at');
            $table->timestamp('archived_at')->nullable()->after('suspended_at');
            $table->text('suspension_reason')->nullable()->after('archived_at');
            $table->timestamp('provisioned_at')->nullable()->after('suspension_reason');
            $table->text('provisioning_error')->nullable()->after('provisioned_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'suspended_at',
                'archived_at',
                'suspension_reason',
                'provisioned_at',
                'provisioning_error',
            ]);
        });
    }
};