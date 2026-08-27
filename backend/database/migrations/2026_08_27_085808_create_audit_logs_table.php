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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Tenant relationship (for multi-tenancy)
            $table->string('tenant_id')->nullable();
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // User who performed the action
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');

            // Action details
            $table->string('action'); // e.g., 'user_logged_in', 'conversation_created', 'ticket_updated'
            $table->string('resource_type')->nullable(); // e.g., 'user', 'conversation', 'ticket', 'customer'
            $table->unsignedBigInteger('resource_id')->nullable(); // ID of the resource

            // Data changes
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Request context
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable(); // Additional context data

            $table->timestamps();

            // Indexes for performance
            $table->index(['tenant_id', 'user_id']);
            $table->index(['resource_type', 'resource_id']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
