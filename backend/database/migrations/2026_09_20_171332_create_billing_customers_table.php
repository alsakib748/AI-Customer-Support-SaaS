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
        // Schema::create('billing_customers', function (Blueprint $table) {
        //     $table->id();
        //     $table->timestamps();
        // });
        if (Schema::connection('central')->hasTable('billing_customers')) {
            return;
        }

        Schema::connection('central')->create('billing_customers', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('provider', 50);
            $table->string('provider_customer_id');
            $table->string('email')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')->cascadeOnDelete();

            $table->unique(['tenant_id', 'provider']);
            $table->unique(['provider', 'provider_customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('billing_customers');
        Schema::connection('central')->dropIfExists('billing_customers');
    }
};
