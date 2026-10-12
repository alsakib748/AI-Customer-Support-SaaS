<?php

// app/Ai/Services/EmbeddingService.php

namespace App\Ai\Services;

use App\Ai\Embeddings\EmbeddingProvider;
use App\Ai\Exceptions\EmbeddingException;
use Illuminate\Support\Facades\Cache;

/**
 * Generates real embeddings through the configured {@see EmbeddingProvider}.
 *
 * Vectors are cached per tenant, per provider, per model, per dimension and
 * per task type. The service never fabricates a vector: if the provider cannot
 * be reached it throws {@see EmbeddingException} so callers can decide whether
 * to skip an article or fall back to keyword search instead of ranking against
 * meaningless data.
 */
class EmbeddingService
{
    public function __construct(protected EmbeddingProvider $provider) {}

    public function provider(): EmbeddingProvider
    {
        return $this->provider;
    }

    public function model(): string
    {
        return $this->provider->model();
    }

    public function dimensions(): int
    {
        return $this->provider->dimensions();
    }

    /**
     * Generate an embedding for a single piece of text.
     *
     * @return array<int, float>
     *
     * @throws EmbeddingException
     */
    public function generateEmbedding(string $text, string $taskType = 'RETRIEVAL_DOCUMENT'): array
    {
        $text = trim($text);

        if ($text === '') {
            throw EmbeddingException::emptyText();
        }

        if (! $this->cacheEnabled()) {
            return $this->provider->embed($text, $taskType);
        }

        $cacheKey = $this->cacheKey($text, $taskType);
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $embedding = $this->provider->embed($text, $taskType);

        Cache::put($cacheKey, $embedding, $this->cacheTtl());

        return $embedding;
    }

    /**
     * Generate embeddings for a batch of texts.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     *
     * @throws EmbeddingException
     */
    public function batchGenerateEmbeddings(array $texts, string $taskType = 'RETRIEVAL_DOCUMENT'): array
    {
        if (! $this->cacheEnabled()) {
            return $this->provider->embedBatch($texts, $taskType);
        }

        $results = [];
        $pending = [];
        $pendingIndexes = [];

        foreach (array_values($texts) as $index => $text) {
            $text = trim((string) $text);

            if ($text === '') {
                continue;
            }

            $cached = Cache::get($this->cacheKey($text, $taskType));

            if (is_array($cached) && $cached !== []) {
                $results[$index] = $cached;

                continue;
            }

            $pendingIndexes[$index] = $text;
            $pending[] = $text;
        }

        if ($pending !== []) {
            $fresh = array_values($this->provider->embedBatch($pending, $taskType));

            $position = 0;
            foreach ($pendingIndexes as $index => $text) {
                $vector = $fresh[$position] ?? [];

                $results[$index] = $vector;
                Cache::put($this->cacheKey($text, $taskType), $vector, $this->cacheTtl());

                $position++;
            }
        }

        ksort($results);

        return array_values($results);
    }

    /**
     * Cosine similarity between two vectors of equal dimension.
     *
     * Returns 0.0 for empty or mismatched vectors rather than throwing: a
     * dimension mismatch means the vectors are incomparable, not similar.
     *
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $count = count($a);

        if ($count === 0 || $count !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Shared cache key for a knowledge base article's chunk vectors.
     *
     * The key embeds the tenant, model and a content version so that a
     * reindexed article never overwrites (or is served from) a stale entry.
     */
    public function articleCacheKey(int $articleId, string $title, string $content, ?int $updatedAt = null): string
    {
        $version = sha1(implode('|', [$title, $content, (string) $updatedAt]));

        return sprintf(
            'ai_article_embeddings:%s:%s:%d:%s',
            $this->tenantKey(),
            $this->provider->model(),
            $articleId,
            $version
        );
    }

    /**
     * Tenant namespace for cache isolation. Falls back to "central" outside a
     * tenant context (e.g. platform jobs and tests).
     */
    public function tenantKey(): string
    {
        if (function_exists('tenant')) {
            $tenant = tenant();

            if ($tenant !== null) {
                return (string) $tenant->getTenantKey();
            }
        }

        return 'central';
    }

    protected function cacheKey(string $text, string $taskType): string
    {
        return sprintf(
            'ai_embedding:%s:%s:%s:%d:%s:%s',
            $this->tenantKey(),
            $this->provider->name(),
            $this->provider->model(),
            $this->provider->dimensions(),
            $taskType,
            sha1($text)
        );
    }

    protected function cacheEnabled(): bool
    {
        return (bool) config('ai.embeddings.cache', true);
    }

    protected function cacheTtl(): int
    {
        return (int) config('ai.embeddings.cache_ttl', 86400);
    }
}
