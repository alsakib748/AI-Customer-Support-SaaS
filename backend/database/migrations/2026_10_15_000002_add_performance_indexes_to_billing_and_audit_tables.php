<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the composite indexes required by the hottest billing and audit
     * queries. PostgreSQL does not index foreign-key columns automatically, so
     * filtering by tenant_id + ordering by created_at currently performs a
     * sequential scan on these tables.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'invoices_tenant_created_idx');
            $table->index(['tenant_id', 'status'], 'invoices_tenant_status_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'payments_tenant_created_idx');
            $table->index(['tenant_id', 'status'], 'payments_tenant_status_idx');
            $table->index(['tenant_id', 'provider'], 'payments_tenant_provider_idx');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['tenant_id', 'status'], 'subscriptions_tenant_status_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'audit_logs_tenant_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_tenant_created_idx');
            $table->dropIndex('invoices_tenant_status_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_tenant_created_idx');
            $table->dropIndex('payments_tenant_status_idx');
            $table->dropIndex('payments_tenant_provider_idx');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('subscriptions_tenant_status_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_tenant_created_idx');
        });
    }
};
