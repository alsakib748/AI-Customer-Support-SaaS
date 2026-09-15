<?php
// app/Services/Analytics/Concerns/HasAnalyticsContext.php

namespace App\Services\Analytics\Concerns;

use App\Support\Analytics\AnalyticsInterval;
use App\Support\Analytics\AnalyticsPeriod;

trait HasAnalyticsContext
{
    protected AnalyticsPeriod $period;
    protected string $interval;
    protected array $filters;

    public function withFilters(array $filters): static
    {
        $this->filters = $filters;
        $this->period = AnalyticsPeriod::fromRequest($filters);
        $this->interval = AnalyticsInterval::determine(
            $this->period,
            $filters['interval'] ?? null
        );

        return $this;
    }

    public function getMeta(): array
    {
        return array_merge($this->period->toArray(), [
            'interval' => $this->interval,
        ]);
    }

    protected function getPostgresDateFormat(): string
    {
        return AnalyticsInterval::getPostgresFormat($this->interval);
    }
}