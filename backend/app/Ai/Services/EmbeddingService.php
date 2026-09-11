<?php
// app/Ai/Services/EmbeddingService.php

namespace App\Ai\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class EmbeddingService
{
    protected $provider;
    protected $model;
    protected $configuration;

    public function __construct()
    {
        $this->configuration = \App\Models\Tenant\AIConfiguration::first()
            ?? \App\Models\Tenant\AIConfiguration::getDefault();

        $this->provider = $this->configuration->provider ?? 'openai';
        $this->model = $this->getEmbeddingModel();
    }

    /**
     * Get embedding model based on provider
     */
    protected function getEmbeddingModel(): string
    {
        $models = [
            'openai' => 'text-embedding-ada-002',
            'anthropic' => 'claude-3-embedding',
            'gemini' => 'embedding-001',
        ];

        return $models[$this->provider] ?? 'text-embedding-ada-002';
    }

    /**
     * Generate embeddings for text
     */
    public function generateEmbedding(string $text): array
    {
        try {
            // Check cache first
            $cacheKey = 'embedding_' . md5($text);

            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            // Get embedding from provider
            $embedding = $this->getEmbeddingFromProvider($text);

            // Cache the embedding
            Cache::put($cacheKey, $embedding, 86400); // 24 hours

            return $embedding;

        } catch (\Exception $e) {
            Log::error('Failed to generate embedding:', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text),
            ]);

            // Return a zero vector as fallback
            return $this->getZeroEmbedding();
        }
    }

    /**
     * Get embedding from provider
     */
    protected function getEmbeddingFromProvider(string $text): array
    {
        // For now, simulate embedding with random vector
        // In production, this would call the actual API
        // $response = Http::withHeaders([
        //     'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
        // ])->post('https://api.openai.com/v1/embeddings', [
        //     'model' => $this->model,
        //     'input' => $text,
        // ]);

        // return $response->json('data.0.embedding');

        // Simulated embedding (1536 dimensions for OpenAI)
        $embedding = [];
        for ($i = 0; $i < 1536; $i++) {
            $embedding[] = rand(-100, 100) / 100;
        }
        return $embedding;
    }

    /**
     * Get zero embedding (fallback)
     */
    protected function getZeroEmbedding(): array
    {
        return array_fill(0, 1536, 0);
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0 || $normB == 0) {
            return 0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Batch generate embeddings
     */
    public function batchGenerateEmbeddings(array $texts): array
    {
        $results = [];
        foreach ($texts as $text) {
            $results[] = $this->generateEmbedding($text);
        }
        return $results;
    }
}
