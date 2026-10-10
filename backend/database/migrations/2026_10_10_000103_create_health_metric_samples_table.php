<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('health_metric_samples', function (Blueprint $table) {
            $table->id();
            $table->string('metric_key', 80)->index();
            $table->string('source', 80)->nullable()->index();
            $table->decimal('value', 20, 4);
            $table->string('unit', 20)->nullable();
            $table->timestampTz('recorded_at')->index();
            $table->timestampsTz();

            $table->index(['metric_key', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('health_metric_samples');
    }
};