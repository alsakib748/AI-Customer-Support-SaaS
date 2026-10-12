<?php

namespace App\Ai\Exceptions;

use RuntimeException;

class EmbeddingException extends RuntimeException
{
    public static function notConfigured(string $provider): self
    {
        return new self("The [{$provider}] embedding provider is not configured.");
    }

    public static function requestFailed(string $provider, string $reason): self
    {
        return new self("The [{$provider}] embedding provider failed: {$reason}");
    }

    public static function invalidResponse(string $provider): self
    {
        return new self("The [{$provider}] embedding provider returned an empty or malformed vector.");
    }

    public static function dimensionMismatch(int $expected, int $actual): self
    {
        return new self("Embedding dimension mismatch: expected {$expected}, received {$actual}.");
    }

    public static function emptyText(): self
    {
        return new self('Cannot generate an embedding for empty text.');
    }
}
