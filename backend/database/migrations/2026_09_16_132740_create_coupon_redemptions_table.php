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
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->string('tenant_id');
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();

            // Discount Applied
            $table->decimal('discount_amount', 10, 2);
            $table->string('currency', 3)->default('USD');

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamp('redeemed_at')->useCurrent();

            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
            $table->unique(['coupon_id', 'tenant_id']);
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
    }
};