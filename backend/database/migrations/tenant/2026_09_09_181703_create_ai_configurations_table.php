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
        Schema::create('ai_configurations', function (Blueprint $table) {
            $table->id();

            $table->string('provider')->default('openai');
            $table->string('model')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('auto_reply_enabled')->default(true);
            $table->boolean('auto_escalation_enabled')->default(true);
            $table->boolean('streaming_enabled')->default(true);
            $table->boolean('knowledge_base_enabled')->default(true);
            $table->decimal('temperature', 3, 2)->default(0.7);
            $table->integer('max_tokens')->default(2000);
            $table->text('system_prompt')->nullable();
            $table->text('custom_instructions')->nullable();
            $table->json('settings')->nullable();

            $table->timestamps();

            $table->index('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_configurations');
    }
};