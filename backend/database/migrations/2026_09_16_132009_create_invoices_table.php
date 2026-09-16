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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            // Relationships
            $table->string('tenant_id');
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();

            // Invoice Number
            $table->string('invoice_number', 50)->unique();

            // Amounts
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');

            // Status
            $table->string('status', 30)->default('draft');
            // draft, open, paid, void, uncollectible, refunded

            // Payment
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_provider')->nullable();
            $table->string('transaction_id')->nullable();

            // Billing Period
            $table->timestamp('period_starts_at')->nullable();
            $table->timestamp('period_ends_at')->nullable();

            // External IDs
            $table->string('stripe_invoice_id')->nullable();
            $table->string('paypal_invoice_id')->nullable();

            // URLs
            $table->string('invoice_pdf_url')->nullable();
            $table->string('hosted_invoice_url')->nullable();

            // Details
            $table->json('line_items')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
            $table->index('status');
            $table->index('invoice_number');
            $table->index('due_at');
            $table->index('stripe_invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};