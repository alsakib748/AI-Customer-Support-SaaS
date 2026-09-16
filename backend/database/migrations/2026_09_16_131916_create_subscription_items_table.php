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
        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();

            // Item Details
            $table->string('type'); // addon, seat, usage
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();

            // Quantity & Pricing
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);

            // External IDs
            $table->string('stripe_item_id')->nullable();
            $table->string('stripe_price_id')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['subscription_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
    }
};
