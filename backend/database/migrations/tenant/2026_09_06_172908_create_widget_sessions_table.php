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
        Schema::create('widget_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('widget_id')
                ->constrained('chat_widgets')
                ->cascadeOnDelete();

            $table->string('visitor_token', 100)->unique();
            $table->string('session_token', 100)->unique();

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            $table->foreignId('current_conversation_id')
                ->nullable()
                ->constrained('conversations')
                ->nullOnDelete();

            $table->json('metadata')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('visitor_token');
            $table->index('session_token');
            $table->index('last_seen_at');
            $table->index('created_at');
            $table->index(['created_at', 'current_conversation_id']);
            $table->index(['last_seen_at', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widget_sessions');
    }
};
