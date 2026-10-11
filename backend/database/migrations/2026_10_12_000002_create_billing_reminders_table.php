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
        Schema::connection('central')->create('billing_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('subscription_id');
            $table->string('type', 20);       // renewal | trial
            $table->unsignedInteger('days_before');
            $table->string('period_key', 40); // billing/trial period the reminder is tied to
            $table->string('status', 20)->default('sent');
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'type', 'days_before', 'period_key'], 'billing_reminders_unique');
            $table->index(['tenant_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('billing_reminders');
    }
};
