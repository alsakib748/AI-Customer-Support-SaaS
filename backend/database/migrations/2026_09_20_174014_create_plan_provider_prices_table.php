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
        if (Schema::connection('central')->hasTable('plan_provider_prices')) {
            return;
        }

        Schema::connection('central')->create('plan_provider_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('billing_cycle', 20);   // monthly | yearly
            $table->string('currency', 3)->default('USD');
            $table->string('provider_price_id');
            $table->decimal('amount', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['plan_id', 'provider', 'billing_cycle', 'currency'],
                'plan_provider_cycle_currency_unique'
            );
            $table->index(['provider', 'provider_price_id'], 'plan_provider_price_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('plan_provider_prices');
        Schema::connection('central')->dropIfExists('plan_provider_prices');
    }
};
