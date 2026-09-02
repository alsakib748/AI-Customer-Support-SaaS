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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();

            // Personal Information
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone', 50)->nullable();
            $table->string('company_name')->nullable();

            // Avatar
            $table->string('avatar')->nullable();

            // Status
            $table->string('status', 20)->default('active'); // active, inactive, blocked

            // Additional Information
            $table->json('metadata')->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();

            // Preferences
            $table->string('default_language', 10)->default('en');
            $table->string('timezone')->default('UTC');

            // Activity Tracking
            $table->timestamp('last_contacted_at')->nullable();
            $table->integer('total_conversations')->default(0);
            $table->integer('total_tickets')->default(0);
            $table->decimal('satisfaction_score', 3, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['first_name', 'last_name']);
            $table->index('email');
            $table->index('phone');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};