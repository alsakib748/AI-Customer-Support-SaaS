<?php

namespace App\Health\DTOs;

use App\Health\Enums\HealthStatus;
use Illuminate\Support\Carbon;

/**
 * Immutable outcome of a single health check.
 */
final readonly class HealthCheckResult
{
    public function __construct(
        public string $key,
        public HealthStatus $status,
        public ?string $summary = null,
        public array $metadata = [],
        public int $durationMs = 0,
        public ?Carbon $checkedAt = null,
    ) {
    }

    public static function make(
        string $key,
        HealthStatus $status,
        ?string $summary = null,
        array $metadata = [],
        ?Carbon $checkedAt = null,
    ): self {
        return new self($key, $status, $summary, $metadata, 0, $checkedAt);
    }

    public function withDuration(int $durationMs): self
    {
        return new self($this->key, $this->status, $this->summary, $this->metadata, $durationMs, $this->checkedAt);
    }

    public function withCheckedAt(Carbon $checkedAt): self
    {
        return new self($this->key, $this->status, $this->summary, $this->metadata, $this->durationMs, $checkedAt);
    }

    public function isHealthy(): bool
    {
        return $this->status->isHealthy();
    }

    public function checkedAtIso(): ?string
    {
        return $this->checkedAt?->toIso8601String();
    }

    public function checkedAtIsStale(Carbon $now, int $staleAfterSeconds): bool
    {
        return $this->checkedAt !== null
            && abs($now->diffInSeconds($this->checkedAt)) > $staleAfterSeconds;
    }

    public function toArray(): array
    {
        return [
            'key'         => $this->key,
            'status'      => $this->status->value,
            'summary'     => $this->summary,
            'metadata'    => $this->metadata,
            'duration_ms' => $this->durationMs,
            'checked_at'  => $this->checkedAtIso(),
        ];
    }
}