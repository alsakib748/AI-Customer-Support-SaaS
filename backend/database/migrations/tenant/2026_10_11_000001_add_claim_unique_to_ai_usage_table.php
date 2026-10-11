<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add a unique claim index so a single customer message can only be
     * processed by the AI once, even under concurrent queue workers.
     */
    public function up(): void
    {
        // Legacy rows may already contain duplicates for the same
        // (message_id, operation) pair; keep the earliest row of each group so
        // the unique index below can be created safely.
        $duplicates = DB::table('ai_usage')
            ->selectRaw('MIN(id) as keep_id, message_id, operation')
            ->whereNotNull('message_id')
            ->groupBy('message_id', 'operation')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('ai_usage')
                ->where('message_id', $duplicate->message_id)
                ->where('operation', $duplicate->operation)
                ->where('id', '<>', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('ai_usage', function (Blueprint $table) {
            $table->unique(['message_id', 'operation'], 'ai_usage_message_operation_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage', function (Blueprint $table) {
            $table->dropUnique('ai_usage_message_operation_unique');
        });
    }
};
