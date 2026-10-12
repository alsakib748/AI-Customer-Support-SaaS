<?php

namespace App\Ai\Embeddings;

use App\Ai\Exceptions\EmbeddingException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Real Google Gemini embedding backend (models/gemini-embedding-001).
 *
 * Uses the provider-specific task hints so that stored documents and live
 * queries are embedded into comparable spaces.
 */
class GeminiEmbeddingProvider implements EmbeddingProvider
{
    protected string $apiKey;

    protected string $baseUrl;

    protected string $model;

    protected int $dimensions;

    public function __construct()
    {
        $this->apiKey = (string) config('ai.providers.gemini.key');
        $this->baseUrl = rtrim((string) config('ai.providers.gemini.url', 'https://generativelanguage.googleapis.com/v1beta/'), '/');
        $this->model = (string) config('ai.embeddings.model', 'gemini-embedding-001');
        $this->dimensions = (int) config('ai.embeddings.dimensions', 768);
    }

    public function name(): string
    {
        return 'gemini';
    }

    public function model(): string
    {
        return $this->model;
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function embed(string $text, string $taskType = 'RETRIEVAL_DOCUMENT'): array
    {
        $this->ensureConfigured();

        if (trim($text) === '') {
            throw EmbeddingException::emptyText();
        }

        try {
            $response = Http::timeout(30)->connectTimeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->endpoint('embedContent'), $this->payload($text, $taskType));
        } catch (Throwable $e) {
            Log::error('Gemini embedding request threw', ['error' => $e->getMessage()]);

            throw EmbeddingException::requestFailed($this->name(), $e->getMessage());
        }

        if (! $response->successful()) {
            Log::error('Gemini embedding request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw EmbeddingException::requestFailed($this->name(), 'HTTP '.$response->status());
        }

        return $this->normaliseVector($response->json('embedding.values'));
    }

    public function embedBatch(array $texts, string $taskType = 'RETRIEVAL_DOCUMENT'): array
    {
        $texts = array_values(array_filter($texts, fn ($t) => trim((string) $t) !== ''));

        if ($texts === []) {
            return [];
        }

        $this->ensureConfigured();

        $requests = array_map(
            fn (string $text) => $this->payload($text, $taskType),
            $texts
        );

        try {
            $response = Http::timeout(60)->connectTimeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->endpoint('batchEmbedContents'), ['requests' => $requests]);
        } catch (Throwable $e) {
            Log::error('Gemini batch embedding request threw', ['error' => $e->getMessage()]);

            throw EmbeddingException::requestFailed($this->name(), $e->getMessage());
        }

        if (! $response->successful()) {
            Log::error('Gemini batch embedding request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw EmbeddingException::requestFailed($this->name(), 'HTTP '.$response->status());
        }

        $embeddings = $response->json('embeddings');

        if (! is_array($embeddings) || count($embeddings) !== count($texts)) {
            throw EmbeddingException::invalidResponse($this->name());
        }

        return array_map(
            fn ($embedding) => $this->normaliseVector($embedding['values'] ?? null),
            $embeddings
        );
    }

    /**
     * @param  array<int, mixed>|null  $values
     * @return array<int, float>
     */
    protected function normaliseVector(mixed $values): array
    {
        if (! is_array($values) || $values === []) {
            throw EmbeddingException::invalidResponse($this->name());
        }

        $vector = array_map(static fn ($value) => (float) $value, array_values($values));

        if ($this->dimensions > 0 && count($vector) !== $this->dimensions) {
            throw EmbeddingException::dimensionMismatch($this->dimensions, count($vector));
        }

        return $vector;
    }

    protected function ensureConfigured(): void
    {
        if ($this->apiKey === '') {
            throw EmbeddingException::notConfigured($this->name());
        }
    }

    protected function endpoint(string $method): string
    {
        return "{$this->baseUrl}/models/{$this->model}:{$method}?key={$this->apiKey}";
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(string $text, string $taskType): array
    {
        $payload = [
            'model' => "models/{$this->model}",
            'content' => [
                'parts' => [
                    ['text' => $text],
                ],
            ],
            'taskType' => $taskType,
        ];

        if ($this->dimensions > 0) {
            $payload['outputDimensionality'] = $this->dimensions;
        }

        return $payload;
    }
}
