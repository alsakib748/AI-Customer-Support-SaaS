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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            // Conversation relationship
            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            // Sender information (polymorphic)
            $table->string('sender_type', 30); // customer, agent, ai, system
            $table->unsignedBigInteger('sender_id')->nullable();

            // Message type
            $table->string('message_type', 30)->default('text');

            // Content
            $table->text('content')->nullable();

            // Internal flag
            $table->boolean('is_internal')->default(false);

            // Additional data
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_type', 'sender_id']);
            $table->index('message_type');
            $table->index('is_internal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};