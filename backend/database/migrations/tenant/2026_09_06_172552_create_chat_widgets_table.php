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
        Schema::create('chat_widgets', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('name');
            $table->string('public_key', 100)->unique();
            $table->string('status', 20)->default('active');

            // Appearance
            $table->string('position', 20)->default('bottom-right');
            $table->string('header_title')->default('Chat with us');
            $table->text('welcome_message')->nullable();
            $table->text('offline_message')->nullable();
            $table->string('primary_color', 20)->nullable();
            $table->string('logo')->nullable();
            $table->string('avatar')->nullable();
            $table->boolean('show_branding')->default(true);

            // Customer Information Requirements
            $table->boolean('require_name')->default(false);
            $table->boolean('require_email')->default(false);
            $table->boolean('require_phone')->default(false);

            // Security
            $table->json('allowed_origins')->nullable();

            // Settings
            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('public_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_widgets');
    }
};
