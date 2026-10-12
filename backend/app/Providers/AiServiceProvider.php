<?php

namespace App\Providers;

use App\Ai\Embeddings\EmbeddingProvider;
use App\Ai\Embeddings\GeminiEmbeddingProvider;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AiServiceProvider extends ServiceProvider
{
    /**
     * Register the AI subsystem bindings.
     */
    public function register(): void
    {
        $this->app->bind(EmbeddingProvider::class, function () {
            $provider = (string) config('ai.embeddings.provider', 'gemini');

            return match ($provider) {
                'gemini' => new GeminiEmbeddingProvider,
                default => throw new InvalidArgumentException("Unsupported embedding provider [{$provider}]."),
            };
        });
    }
}
