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
        Schema::connection('central')->table('subscriptions', function (Blueprint $table) {
            if (!Schema::connection('central')->hasColumn('subscriptions', 'provider')) {
                $table->string('provider', 50)->nullable()->after('plan_id');
            }
            if (!Schema::connection('central')->hasColumn('subscriptions', 'provider_customer_id')) {
                $table->string('provider_customer_id')->nullable()->after('provider');
            }
            if (!Schema::connection('central')->hasColumn('subscriptions', 'provider_subscription_id')) {
                $table->string('provider_subscription_id')->nullable()->after('provider_customer_id');
            }
            if (!Schema::connection('central')->hasColumn('subscriptions', 'provider_price_id')) {
                $table->string('provider_price_id')->nullable()->after('provider_subscription_id');
            }
            if (!Schema::connection('central')->hasColumn('subscriptions', 'current_period_starts_at')) {
                $table->timestamp('current_period_starts_at')->nullable()->after('provider_price_id');
                $table->timestamp('current_period_ends_at')->nullable()->after('current_period_starts_at');
            }
            if (!Schema::connection('central')->hasColumn('subscriptions', 'cancel_at_period_end')) {
                $table->boolean('cancel_at_period_end')->default(false)->after('auto_renew');
            }

            $table->index('provider_subscription_id', 'subs_provider_sub_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('subs_provider_sub_id_idx');
            $table->dropColumn([
                'provider', 'provider_customer_id', 'provider_subscription_id',
                'provider_price_id', 'current_period_starts_at',
                'current_period_ends_at', 'cancel_at_period_end',
            ]);
        });
    }
};
