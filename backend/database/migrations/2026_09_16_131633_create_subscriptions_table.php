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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            // Relationships
            $table->string('tenant_id');
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();

            // Status
            $table->string('status', 30)->default('trialing');
            // trialing, active, past_due, cancelled, expired, paused

            // Billing Period
            $table->string('billing_cycle', 20)->default('monthly');
            // monthly, yearly
            $table->timestamp('trial_starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('resumed_at')->nullable();

            // Renewal
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('last_billing_at')->nullable();

            // Usage Tracking
            $table->integer('ai_used')->default(0);
            $table->integer('ai_limit')->default(1000);
            $table->integer('agents_used')->default(0);
            $table->integer('agents_limit')->default(5);
            $table->integer('documents_used')->default(0);
            $table->integer('documents_limit')->default(100);
            $table->bigInteger('storage_used')->default(0);
            $table->bigInteger('storage_limit')->default(1073741824); // 1GB
            $table->integer('conversations_used')->default(0);
            $table->integer('conversations_limit')->default(500);

            // External IDs
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('paypal_subscription_id')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
            $table->index('status');
            $table->index('next_billing_at');
            $table->index('stripe_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};