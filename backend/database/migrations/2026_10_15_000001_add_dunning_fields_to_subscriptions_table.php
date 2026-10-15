<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'past_due_at')) {
                $table->timestamp('past_due_at')->nullable();
            }

            if (! Schema::hasColumn('subscriptions', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable();
            }

            if (! Schema::hasColumn('subscriptions', 'suspension_reason')) {
                $table->text('suspension_reason')->nullable();
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            // Speeds up the daily dunning scan (status + aging column).
            try {
                $table->index(['status', 'past_due_at'], 'subscriptions_status_past_due_at_index');
            } catch (Throwable $e) {
                // Index already exists (e.g. re-run) — safe to ignore.
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            try {
                $table->dropIndex('subscriptions_status_past_due_at_index');
            } catch (Throwable $e) {
                // nothing to drop
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            foreach (['past_due_at', 'suspended_at', 'suspension_reason'] as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
