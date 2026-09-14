<?php
// app/Support/Analytics/AnalyticsPeriod.php

namespace App\Support\Analytics;

use Carbon\Carbon;
use Illuminate\Support\Str;

class AnalyticsPeriod
{
    public Carbon $from;
    public Carbon $to;
    public ?Carbon $previousFrom;
    public ?Carbon $previousTo;
    public string $label;
    public string $key;

    public function __construct(
        Carbon $from,
        Carbon $to,
        string $label = 'Custom',
        string $key = 'custom'
    ) {
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->endOfDay();
        $this->label = $label;
        $this->key = $key;

        // Calculate previous period (same duration, immediately before)
        $duration = $this->from->diffInSeconds($this->to);
        $this->previousTo = $this->from->copy()->subSecond();
        $this->previousFrom = $this->previousTo->copy()->subSeconds($duration);
    }

    public static function fromRequest(array $filters): self
    {
        // Custom range takes priority
        if (!empty($filters['from']) && !empty($filters['to'])) {
            return new self(
                Carbon::parse($filters['from']),
                Carbon::parse($filters['to']),
                'Custom Range',
                'custom'
            );
        }

        $period = $filters['period'] ?? '30d';

        return match ($period) {
            'today' => new self(
                Carbon::today(),
                Carbon::today(),
                'Today',
                'today'
            ),
            'yesterday' => new self(
                Carbon::yesterday(),
                Carbon::yesterday(),
                'Yesterday',
                'yesterday'
            ),
            '7d' => new self(
                Carbon::today()->subDays(6),
                Carbon::today(),
                'Last 7 Days',
                '7d'
            ),
            '30d' => new self(
                Carbon::today()->subDays(29),
                Carbon::today(),
                'Last 30 Days',
                '30d'
            ),
            '90d' => new self(
                Carbon::today()->subDays(89),
                Carbon::today(),
                'Last 90 Days',
                '90d'
            ),
            'this_month' => new self(
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
                'This Month',
                'this_month'
            ),
            'last_month' => new self(
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->endOfMonth(),
                'Last Month',
                'last_month'
            ),
            'this_year' => new self(
                Carbon::now()->startOfYear(),
                Carbon::now()->endOfYear(),
                'This Year',
                'this_year'
            ),
            default => new self(
                Carbon::today()->subDays(29),
                Carbon::today(),
                'Last 30 Days',
                '30d'
            ),
        };
    }

    public function getDurationInDays(): int
    {
        return $this->from->diffInDays($this->to) + 1;
    }

    public function toArray(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'label' => $this->label,
            'key' => $this->key,
            'previous_from' => $this->previousFrom?->toDateString(),
            'previous_to' => $this->previousTo?->toDateString(),
        ];
    }
}