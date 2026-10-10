<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('platform_incident_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('incident_id');
            $table->string('event', 20)->index();
            $table->string('severity', 20)->nullable();
            $table->text('summary')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampTz('created_at')->index();
            $table->timestampTz('updated_at')->nullable();

            $table->foreign('incident_id')->references('id')->on('platform_incidents')->cascadeOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('platform_incident_events');
    }
};