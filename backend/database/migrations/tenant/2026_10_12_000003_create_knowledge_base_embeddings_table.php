<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Durable, tenant-scoped storage for knowledge base chunk embeddings.
     *
     * Embeddings live in the tenant database so each tenant's vectors are
     * isolated by connection. The model + article_version columns let a
     * provider/model swap or article edit invalidate only the affected rows
     * instead of silently serving incomparable or stale vectors.
     */
    public function up(): void
    {
        Schema::create('knowledge_base_embeddings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('article_id')
                ->constrained('knowledge_base_articles')
                ->cascadeOnDelete();

            $table->unsignedInteger('chunk_index')->default(0);
            $table->longText('content');
            $table->string('content_hash', 64);
            $table->string('article_version', 64);
            $table->json('embedding');
            $table->string('model');
            $table->unsignedInteger('dimensions')->default(0);

            $table->timestamps();

            $table->unique(['article_id', 'chunk_index', 'model'], 'kb_embeddings_article_chunk_model_unique');
            $table->index(['article_id', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_embeddings');
    }
};
