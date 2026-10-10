<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('platform_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 120)->index();
            $table->string('title', 160);
            $table->text('summary')->nullable();
            $table->string('severity', 20)->index();
            $table->string('status', 20)->index();
            $table->string('source', 60)->index();
            $table->timestampTz('started_at')->index();
            $table->timestampTz('acknowledged_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'severity']);

            $table->foreign('acknowledged_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('platform_incidents');
    }
};