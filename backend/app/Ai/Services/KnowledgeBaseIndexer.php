<?php

namespace App\Ai\Services;

use App\Ai\Exceptions\EmbeddingException;
use App\Models\Tenant\KnowledgeBaseArticle;
use App\Models\Tenant\KnowledgeBaseEmbedding;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Persists and retrieves knowledge base chunk embeddings.
 *
 * Embeddings live in the tenant database (see the
 * knowledge_base_embeddings migration), which keeps them durable across cache
 * flushes and isolated per tenant. Rows are versioned by article content and
 * embedding model so stale vectors are never served.
 */
class KnowledgeBaseIndexer
{
    public function __construct(
        protected EmbeddingService $embeddingService,
        protected KnowledgeBaseChunkingService $chunkingService,
    ) {}

    /**
     * Stable hash of the article content that produced a set of embeddings.
     */
    public function articleVersion(KnowledgeBaseArticle $article): string
    {
        return sha1(implode('|', [
            (string) $article->title,
            (string) $article->content,
            (string) ($article->updated_at?->timestamp ?? ''),
        ]));
    }

    /**
     * Return the (freshly ensured) embeddings for an article.
     *
     * @return array<int, array{content: string, embedding: array<int, float>, index: int, content_hash: string}>
     *
     * @throws EmbeddingException when the provider cannot generate vectors.
     */
    public function embeddingsFor(KnowledgeBaseArticle $article): array
    {
        $model = $this->embeddingService->model();
        $version = $this->articleVersion($article);

        $rows = KnowledgeBaseEmbedding::query()
            ->where('article_id', $article->id)
            ->where('model', $model)
            ->orderBy('chunk_index')
            ->get();

        if (! $this->isFresh($rows, $version, $this->embeddingService->dimensions())) {
            $this->reindex($article);

            $rows = KnowledgeBaseEmbedding::query()
                ->where('article_id', $article->id)
                ->where('model', $model)
                ->orderBy('chunk_index')
                ->get();
        }

        return $rows->map(fn (KnowledgeBaseEmbedding $row) => [
            'content' => $row->content,
            'embedding' => $row->embedding ?? [],
            'index' => $row->chunk_index,
            'content_hash' => $row->content_hash,
        ])->all();
    }

    /**
     * (Re)build the persisted embeddings for an article.
     *
     * @return int The number of chunks indexed (0 when the article has no content).
     *
     * @throws EmbeddingException when the provider cannot generate vectors.
     */
    public function reindex(KnowledgeBaseArticle $article): int
    {
        $chunks = $this->chunkingService->chunkArticle($article);

        if ($chunks === []) {
            $this->purge($article);

            return 0;
        }

        $vectors = $this->embeddingService->batchGenerateEmbeddings(
            array_map(fn ($chunk) => $chunk['content'], $chunks),
            'RETRIEVAL_DOCUMENT'
        );

        $model = $this->embeddingService->model();
        $version = $this->articleVersion($article);
        $now = Carbon::now();

        $connection = (new KnowledgeBaseEmbedding)->getConnection();

        $connection->transaction(function () use ($article, $chunks, $vectors, $model, $version, $now) {
            KnowledgeBaseEmbedding::query()
                ->where('article_id', $article->id)
                ->where('model', $model)
                ->delete();

            $rows = [];
            foreach ($chunks as $position => $chunk) {
                $vector = $vectors[$position] ?? [];

                $rows[] = [
                    'article_id' => $article->id,
                    'chunk_index' => $chunk['index'],
                    'content' => $chunk['content'],
                    'content_hash' => sha1($chunk['content']),
                    'article_version' => $version,
                    'embedding' => json_encode($vector),
                    'model' => $model,
                    'dimensions' => count($vector),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            KnowledgeBaseEmbedding::query()->insert($rows);
        });

        Log::info('Knowledge base article indexed', [
            'article_id' => $article->id,
            'chunks' => count($chunks),
            'model' => $model,
        ]);

        return count($chunks);
    }

    /**
     * Remove all persisted embeddings for an article.
     */
    public function purge(KnowledgeBaseArticle $article): int
    {
        return $this->purgeById((int) $article->getKey());
    }

    /**
     * Remove all persisted embeddings by article id (works for soft-deleted rows).
     */
    public function purgeById(int $articleId): int
    {
        return KnowledgeBaseEmbedding::query()
            ->where('article_id', $articleId)
            ->delete();
    }

    /**
     * @param  Collection<int, KnowledgeBaseEmbedding>  $rows
     */
    protected function isFresh($rows, string $version, int $dimensions): bool
    {
        if ($rows->isEmpty()) {
            return false;
        }

        foreach ($rows as $row) {
            if ($row->article_version !== $version) {
                return false;
            }

            if ($dimensions > 0 && (int) $row->dimensions !== $dimensions) {
                return false;
            }
        }

        return true;
    }
}
