<?php

namespace Tests\Feature\Ai;

use App\Ai\Embeddings\EmbeddingProvider;
use App\Ai\Exceptions\EmbeddingException;
use App\Ai\Services\KnowledgeBaseIndexer;
use App\Ai\Services\SemanticSearchService;
use App\Models\Tenant\KnowledgeBaseArticle;
use App\Models\Tenant\KnowledgeBaseEmbedding;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises durable, tenant-DB-backed embedding storage and retrieval.
 */
class KnowledgeBaseIndexerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'tenant',
            'database.connections.tenant' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'ai.embeddings.dimensions' => 3,
            'ai.embeddings.cache' => false,
        ]);

        DB::purge('tenant');

        $this->createTenantSchema();
    }

    protected function createTenantSchema(): void
    {
        Schema::create('knowledge_base_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_base_articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('status')->default('draft');
            $table->string('visibility')->default('ai');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('knowledge_base_embeddings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('article_id');
            $table->unsignedInteger('chunk_index');
            $table->longText('content');
            $table->string('content_hash');
            $table->string('article_version');
            $table->json('embedding');
            $table->string('model');
            $table->unsignedInteger('dimensions');
            $table->timestamps();
            $table->unique(['article_id', 'chunk_index', 'model']);
        });
    }

    protected function fakeProvider(): EmbeddingProvider
    {
        return new class implements EmbeddingProvider
        {
            public array $calls = [];

            public function name(): string
            {
                return 'fake';
            }

            public function model(): string
            {
                return 'fake-model';
            }

            public function dimensions(): int
            {
                return 3;
            }

            public function embed(string $text, string $taskType = 'RETRIEVAL_DOCUMENT'): array
            {
                $this->calls['embed'] = ($this->calls['embed'] ?? 0) + 1;

                return [1.0, 0.0, 0.0];
            }

            public function embedBatch(array $texts, string $taskType = 'RETRIEVAL_DOCUMENT'): array
            {
                $this->calls['batch'] = ($this->calls['batch'] ?? 0) + 1;

                return array_map(fn () => [1.0, 0.0, 0.0], array_values($texts));
            }
        };
    }

    protected function article(array $attributes = []): KnowledgeBaseArticle
    {
        return KnowledgeBaseArticle::create(array_merge([
            'title' => 'Refund policy',
            'slug' => 'refund-policy-'.uniqid(),
            'content' => 'Refunds are processed within five business days.',
            'status' => 'published',
            'visibility' => 'ai',
            'published_at' => now(),
        ], $attributes));
    }

    protected function indexer(EmbeddingProvider $provider): KnowledgeBaseIndexer
    {
        $this->app->instance(EmbeddingProvider::class, $provider);

        return $this->app->make(KnowledgeBaseIndexer::class);
    }

    public function test_reindex_persists_embeddings_in_tenant_database(): void
    {
        $indexer = $this->indexer($this->fakeProvider());

        $article = $this->article();

        $chunks = $indexer->reindex($article);

        $this->assertGreaterThan(0, $chunks);
        $this->assertSame($chunks, KnowledgeBaseEmbedding::where('article_id', $article->id)->count());

        $row = KnowledgeBaseEmbedding::where('article_id', $article->id)->first();
        $this->assertSame('fake-model', $row->model);
        $this->assertSame(3, $row->dimensions);
        $this->assertEquals([1.0, 0.0, 0.0], $row->embedding);
    }

    public function test_embeddings_are_reused_until_article_content_changes(): void
    {
        $provider = $this->fakeProvider();
        $indexer = $this->indexer($provider);

        $article = $this->article();

        $indexer->embeddingsFor($article);
        $this->assertSame(1, $provider->calls['batch'] ?? 0);

        // Unchanged article: served from the tenant DB, no provider call.
        $indexer->embeddingsFor($article);
        $this->assertSame(1, $provider->calls['batch'] ?? 0);

        // Changed content: the stale rows are refreshed.
        $article->update(['content' => 'Refunds are now processed within two business days.']);
        $indexer->embeddingsFor($article->fresh());

        $this->assertSame(2, $provider->calls['batch'] ?? 0);
    }

    public function test_reindex_replaces_previous_rows(): void
    {
        $indexer = $this->indexer($this->fakeProvider());

        $article = $this->article();

        $first = $indexer->reindex($article);
        $indexer->reindex($article);

        $this->assertSame($first, KnowledgeBaseEmbedding::where('article_id', $article->id)->count());
    }

    public function test_purge_removes_persisted_rows(): void
    {
        $indexer = $this->indexer($this->fakeProvider());

        $article = $this->article();
        $indexer->reindex($article);

        $this->assertSame(1, $indexer->purge($article));
        $this->assertSame(0, KnowledgeBaseEmbedding::where('article_id', $article->id)->count());
    }

    public function test_semantic_search_returns_only_eligible_published_sources(): void
    {
        $this->app->instance(EmbeddingProvider::class, $this->fakeProvider());

        $eligible = $this->article(['title' => 'Refund policy', 'visibility' => 'ai']);
        $this->article(['title' => 'Internal note', 'visibility' => 'internal', 'slug' => 'internal-'.uniqid()]);
        $this->article(['title' => 'Draft article', 'status' => 'draft', 'slug' => 'draft-'.uniqid()]);

        $results = $this->app->make(SemanticSearchService::class)
            ->semanticSearch('how long do refunds take', 5);

        $this->assertCount(1, $results);
        $this->assertSame($eligible->id, $results[0]['article_id']);
        $this->assertArrayHasKey('content', $results[0]);
        $this->assertGreaterThan(0.5, $results[0]['similarity']);
    }

    public function test_reindex_does_not_persist_when_provider_fails(): void
    {
        $failing = new class implements EmbeddingProvider
        {
            public function name(): string
            {
                return 'fake';
            }

            public function model(): string
            {
                return 'fake-model';
            }

            public function dimensions(): int
            {
                return 3;
            }

            public function embed(string $text, string $taskType = 'RETRIEVAL_DOCUMENT'): array
            {
                throw EmbeddingException::requestFailed('fake', 'down');
            }

            public function embedBatch(array $texts, string $taskType = 'RETRIEVAL_DOCUMENT'): array
            {
                throw EmbeddingException::requestFailed('fake', 'boom');
            }
        };

        $indexer = $this->indexer($failing);
        $article = $this->article();

        try {
            $indexer->reindex($article);
            $this->fail('Expected EmbeddingException.');
        } catch (EmbeddingException) {
            // expected
        }

        $this->assertSame(0, KnowledgeBaseEmbedding::where('article_id', $article->id)->count());
    }
}
