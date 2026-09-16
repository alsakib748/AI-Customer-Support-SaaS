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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            // Code
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Discount
            $table->string('type', 20); // percentage, fixed_amount
            $table->decimal('value', 10, 2);
            $table->string('currency', 3)->default('USD');

            // Duration
            $table->string('duration', 20)->default('once');
            // once, repeating, forever
            $table->integer('duration_months')->nullable();

            // Limits
            $table->integer('max_redemptions')->nullable();
            $table->integer('times_redeemed')->default(0);
            $table->integer('max_redemptions_per_tenant')->default(1);

            // Restrictions
            $table->json('applicable_plans')->nullable();
            $table->decimal('minimum_amount', 10, 2)->nullable();

            // Validity
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('code');
            $table->index('is_active');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
