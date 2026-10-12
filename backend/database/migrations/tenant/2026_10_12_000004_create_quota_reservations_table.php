<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * In-flight quota reservations.
     *
     * `check()`/`enforce()` read the current usage and compare it to the plan
     * limit, which races under concurrency. This table lets the AI paths claim
     * a slot with a single atomic conditional UPDATE before the provider is
     * called. One row is kept per feature + accounting period.
     */
    public function up(): void
    {
        Schema::create('quota_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('feature');
            $table->string('period', 32);
            $table->unsignedBigInteger('reserved_count')->default(0);
            $table->timestamps();

            $table->unique(['feature', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_reservations');
    }
};
