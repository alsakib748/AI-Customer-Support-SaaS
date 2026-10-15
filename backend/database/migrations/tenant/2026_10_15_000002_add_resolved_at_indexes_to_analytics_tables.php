<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Analytics range-scan and group conversations/tickets by resolved_at
     * (DATE_TRUNC + WHERE BETWEEN). Neither column was indexed, forcing a
     * sequential scan of the whole table per analytics request.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->index('resolved_at');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->index('resolved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['resolved_at']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['resolved_at']);
        });
    }
};
