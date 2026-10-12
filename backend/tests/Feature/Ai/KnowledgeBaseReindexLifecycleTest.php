<?php

namespace Tests\Feature\Ai;

use App\Ai\Embeddings\EmbeddingProvider;
use App\Ai\Services\KnowledgeBaseIndexer;
use App\Jobs\AI\ReindexKnowledgeBaseJob;
use App\Models\Tenant;
use App\Models\Tenant\KnowledgeBaseArticle;
use App\Models\Tenant\KnowledgeBaseEmbedding;
use App\Services\KnowledgeBase\KnowledgeBaseArticleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Verifies that knowledge base lifecycle events queue a tenant-scoped reindex
 * and that the job indexes or purges based on article eligibility.
 */
class KnowledgeBaseReindexLifecycleTest extends TestCase
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
        });
    }

    protected function bindTenant(string $id): void
    {
        $tenant = new Tenant;
        $tenant->id = $id;
        $this->app->instance('current_tenant', $tenant);
    }

    protected function fakeProvider(): EmbeddingProvider
    {
        return new class implements EmbeddingProvider
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
                return [1.0, 0.0, 0.0];
            }

            public function embedBatch(array $texts, string $taskType = 'RETRIEVAL_DOCUMENT'): array
            {
                return array_map(fn () => [1.0, 0.0, 0.0], array_values($texts));
            }
        };
    }

    protected function article(array $attributes = []): KnowledgeBaseArticle
    {
        return KnowledgeBaseArticle::create(array_merge([
            'title' => 'Refund policy',
            'slug' => 'refund-'.uniqid(),
            'content' => 'Refunds within five days.',
            'status' => 'published',
            'visibility' => 'ai',
            'published_at' => now(),
        ], $attributes));
    }

    public function test_publishing_an_article_queues_a_tenant_scoped_reindex(): void
    {
        $this->bindTenant('tenant-kb');
        Queue::fake();

        $service = $this->app->make(KnowledgeBaseArticleService::class);
        $article = $this->article(['status' => 'draft']);
        $service->publishArticle($article);

        Queue::assertPushed(ReindexKnowledgeBaseJob::class, function ($job) use ($article) {
            $reflection = new \ReflectionObject($job);
            $articleId = $reflection->getProperty('articleId');
            $articleId->setAccessible(true);
            $tenantId = $reflection->getProperty('tenantId');
            $tenantId->setAccessible(true);

            return $articleId->getValue($job) === $article->id
                && $tenantId->getValue($job) === 'tenant-kb';
        });
    }

    public function test_deleting_an_article_queues_a_reindex(): void
    {
        $this->bindTenant('tenant-kb');
        Queue::fake();

        $service = $this->app->make(KnowledgeBaseArticleService::class);
        $article = $this->article();
        $service->deleteArticle($article);

        Queue::assertPushed(ReindexKnowledgeBaseJob::class);
    }

    public function test_job_indexes_eligible_article(): void
    {
        $this->app->instance(EmbeddingProvider::class, $this->fakeProvider());

        $article = $this->article();

        (new ReindexKnowledgeBaseJob($article->id))->handle($this->app->make(KnowledgeBaseIndexer::class));

        $this->assertGreaterThan(0, KnowledgeBaseEmbedding::where('article_id', $article->id)->count());
    }

    public function test_job_purges_ineligible_article(): void
    {
        $this->app->instance(EmbeddingProvider::class, $this->fakeProvider());
        $indexer = $this->app->make(KnowledgeBaseIndexer::class);

        $article = $this->article();
        $indexer->reindex($article);
        $this->assertGreaterThan(0, KnowledgeBaseEmbedding::where('article_id', $article->id)->count());

        // Article becomes internal-only: embeddings must be purged.
        $article->update(['visibility' => 'internal']);
        (new ReindexKnowledgeBaseJob($article->id))->handle($indexer);

        $this->assertSame(0, KnowledgeBaseEmbedding::where('article_id', $article->id)->count());
    }
}
