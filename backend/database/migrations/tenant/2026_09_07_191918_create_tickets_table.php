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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            // Ticket Identification
            $table->string('ticket_number', 30)->unique();

            // Relationships
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('conversations')
                ->nullOnDelete();

            // Ticket Details
            $table->string('subject');
            $table->text('description')->nullable();

            // Status & Workflow
            $table->string('status', 30)->default('open');
            $table->string('priority', 20)->default('normal');
            $table->string('type', 30)->default('general');
            $table->string('source', 30)->default('manual');

            // Assignment
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            // Timestamps
            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // Additional Data
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['status', 'priority']);
            $table->index(['customer_id', 'status']);
            $table->index(['assigned_user_id', 'status']);
            $table->index('conversation_id');
            $table->index('ticket_number');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
