<?php

namespace App\Ai\Embeddings;

use App\Ai\Exceptions\EmbeddingException;

/**
 * Contract for a real text-embedding backend.
 *
 * Implementations MUST talk to a real provider and MUST throw an
 * {@see EmbeddingException} when a vector cannot be produced. They must never
 * fabricate, pad, or fall back to a zero/random vector: fabricated vectors
 * silently corrupt semantic search results while looking healthy.
 */
interface EmbeddingProvider
{
    /**
     * Stable provider key used for cache namespacing and model versioning.
     */
    public function name(): string;

    /**
     * The embedding model identifier (e.g. gemini-embedding-001).
     */
    public function model(): string;

    /**
     * The number of dimensions the provider returns.
     */
    public function dimensions(): int;

    /**
     * Embed a single piece of text.
     *
     * @param  string  $taskType  Provider task hint, e.g. RETRIEVAL_DOCUMENT or RETRIEVAL_QUERY.
     * @return array<int, float>
     *
     * @throws EmbeddingException when the provider is unavailable or misconfigured.
     */
    public function embed(string $text, string $taskType = 'RETRIEVAL_DOCUMENT'): array;

    /**
     * Embed a batch of texts.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     *
     * @throws EmbeddingException when the provider is unavailable or misconfigured.
     */
    public function embedBatch(array $texts, string $taskType = 'RETRIEVAL_DOCUMENT'): array;
}
