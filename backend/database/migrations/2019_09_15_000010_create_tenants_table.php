<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subdomain')->nullable()->unique();
            $table->string('domain')->nullable()->unique();
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->string('industry')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('default_language', 10)->default('en');
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            $table->json('business_hours')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();

            // your custom columns may go here

            $table->timestamps();
            $table->json('data')->nullable();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['slug', 'subdomain', 'domain']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
