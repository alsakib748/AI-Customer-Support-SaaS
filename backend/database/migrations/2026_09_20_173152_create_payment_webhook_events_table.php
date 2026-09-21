<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::connection('central')->hasTable('payment_webhook_events')) {
            return;
        }

        Schema::connection('central')->create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('provider_event_id', 191);
            $table->string('event_type', 100)->nullable();
            $table->string('status', 30)->default('received'); // received | processed | failed | ignored
            $table->json('payload');
            $table->json('normalized_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_event_id'], 'webhook_provider_event_unique');
            $table->index(['provider', 'event_type'], 'webhook_provider_type_idx');
            $table->index('status', 'webhook_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('payment_webhook_events');
        Schema::connection('central')->dropIfExists('payment_webhook_events');
    }
};
