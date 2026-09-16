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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            // Relationships
            $table->string('tenant_id');
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();

            // Payment Details
            $table->string('payment_id', 255)->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');

            // Status
            $table->string('status', 30)->default('pending');
            // pending, processing, completed, failed, refunded, partially_refunded

            // Provider
            $table->string('provider', 50); // stripe, paypal, manual
            $table->string('payment_method', 50); // card, bank_transfer, paypal
            $table->string('last_four', 4)->nullable();
            $table->string('card_brand')->nullable();

            // External IDs
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_charge_id')->nullable();
            $table->string('paypal_transaction_id')->nullable();

            // Refund
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->text('refund_reason')->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
            $table->index('status');
            $table->index('provider');
            $table->index('payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};