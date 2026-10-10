<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('health_check_results', function (Blueprint $table) {
            $table->id();
            $table->string('check_key', 80)->index();
            $table->string('status', 20)->index();
            $table->text('summary')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestampTz('checked_at')->index();
            $table->timestampsTz();

            $table->index(['check_key', 'checked_at']);
            $table->index(['status', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('health_check_results');
    }
};