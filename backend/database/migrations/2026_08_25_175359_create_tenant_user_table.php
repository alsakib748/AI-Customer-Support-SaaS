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
        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Role & Permissions
            $table->string('role', 50)->default('member');
            $table->json('permissions')->nullable();

            // Agent Specific
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('availability_status', 20)->default('online');
            $table->integer('max_concurrent_chats')->default(5);
            $table->json('skills')->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();

            $table->timestamps();

            // Unique constraint
            $table->unique(['tenant_id', 'user_id']);

            // Foreign key with proper cascade
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // Indexes
            $table->index('role');
            $table->index('availability_status');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_tenant_user');
    }
};
