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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            // Customer relationship
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Basic conversation info
            $table->string('subject')->nullable();
            $table->string('channel', 30)->default('web');
            $table->string('status', 30)->default('open');
            $table->string('priority', 20)->default('normal');

            // Assignment (no FK - users are in central DB)
            $table->unsignedBigInteger('assigned_user_id')->nullable();

            // Timestamps for tracking
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // Additional data
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['status', 'priority']);
            $table->index(['customer_id', 'status']);
            $table->index(['assigned_user_id', 'status']);
            $table->index('last_message_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};