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
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('feature_key', 100);
            $table->string('feature_type', 30)->default('limit'); // limit | boolean | value
            $table->string('value')->nullable();
            $table->boolean('is_enabled')->default(true);

            $table->timestamps();

            $table->unique(['plan_id', 'feature_key']);
            $table->index('feature_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};