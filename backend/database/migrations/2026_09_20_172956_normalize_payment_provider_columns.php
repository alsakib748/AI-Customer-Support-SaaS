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
        Schema::connection('central')->table('payments', function (Blueprint $table) {
            if (!Schema::connection('central')->hasColumn('payments', 'provider_payment_id')) {
                $table->string('provider_payment_id')->nullable()->after('provider');
            }
            if (!Schema::connection('central')->hasColumn('payments', 'provider_invoice_id')) {
                $table->string('provider_invoice_id')->nullable()->after('provider_payment_id');
            }
            if (!Schema::connection('central')->hasColumn('payments', 'provider_subscription_id')) {
                $table->string('provider_subscription_id')->nullable()->after('provider_invoice_id');
            }

            $table->index('provider_payment_id', 'pay_provider_pay_idx');
            $table->index('provider_invoice_id', 'pay_provider_inv_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->table('payments', function (Blueprint $table) {
            $table->dropIndex('pay_provider_pay_idx');
            $table->dropIndex('pay_provider_inv_idx');
            $table->dropColumn([
                'provider_payment_id', 'provider_invoice_id', 'provider_subscription_id',
            ]);
        });
    }
};
