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
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'widgets_used')) {
                $table->integer('widgets_used')->default(0)->after('agents_limit');
                $table->integer('widgets_limit')->default(1)->after('widgets_used');
            }

            if (!Schema::hasColumn('subscriptions', 'customers_used')) {
                $table->integer('customers_used')->default(0)->after('widgets_limit');
                $table->integer('customers_limit')->default(500)->after('customers_used');
            }

            if (!Schema::hasColumn('subscriptions', 'messages_used')) {
                $table->integer('messages_used')->default(0)->after('customers_limit');
                $table->integer('messages_limit')->default(0)->after('messages_used');
            }

            if (!Schema::hasColumn('subscriptions', 'kb_articles_used')) {
                $table->integer('kb_articles_used')->default(0)->after('messages_limit');
                $table->integer('kb_articles_limit')->default(20)->after('kb_articles_used');
            }

            if (!Schema::hasColumn('subscriptions', 'ai_requests_used')) {
                $table->integer('ai_requests_used')->default(0)->after('kb_articles_limit');
                $table->integer('ai_requests_limit')->default(100)->after('ai_requests_used');
            }

            if (!Schema::hasColumn('subscriptions', 'ai_tokens_used')) {
                $table->bigInteger('ai_tokens_used')->default(0)->after('ai_requests_limit');
                $table->bigInteger('ai_tokens_limit')->default(50000)->after('ai_tokens_used');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'widgets_used',
                'widgets_limit',
                'customers_used',
                'customers_limit',
                'messages_used',
                'messages_limit',
                'kb_articles_used',
                'kb_articles_limit',
                'ai_requests_used',
                'ai_requests_limit',
                'ai_tokens_used',
                'ai_tokens_limit',
            ]);
        });
    }
};