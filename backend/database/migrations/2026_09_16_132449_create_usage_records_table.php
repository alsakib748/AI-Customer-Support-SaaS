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
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id');
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();

            // Usage Type
            $table->string('type'); // ai_tokens, conversations, agents, documents, storage
            $table->bigInteger('quantity')->default(0);
            $table->string('unit')->default('count');

            // Period
            $table->date('period_date');
            $table->string('period_type', 20)->default('daily'); // daily, monthly

            // Cost (if metered)
            $table->decimal('cost', 10, 6)->default(0);

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
            $table->unique(['tenant_id', 'type', 'period_date', 'period_type']);
            $table->index('type');
            $table->index('period_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
