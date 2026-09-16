<?php
// app/Services/Analytics/BaseAnalyticsService.php

namespace App\Services\Analytics;

use App\Support\Analytics\AnalyticsInterval;
use App\Support\Analytics\AnalyticsPeriod;
use App\Support\Analytics\MetricChange;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

abstract class BaseAnalyticsService
{
    protected AnalyticsPeriod $period;
    protected string $interval;
    protected array $filters;
    protected int $cacheTtl = 60; // seconds

    public function __construct()
    {
        // Will be set via withFilters
    }

    /**
     * Set filters and derive period/interval.
     */
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

    /**
     * Get cache key for current filters.
     */
    protected function cacheKey(string $metric): string
    {
        $tenantId = tenant()?->id ?? 'none';
        $key = 'analytics:' . $tenantId . ':' . $metric . ':' .
            md5(json_encode($this->filters ?? []));

        return $key;
    }

    /**
     * Remember analytics result with TTL.
     */
    protected function remember(string $metric, \Closure $callback): mixed
    {
        if (!config('app.debug')) {
            return Cache::remember(
                $this->cacheKey($metric),
                now()->addSeconds($this->cacheTtl),
                $callback
            );
        }

        return $callback();
    }

    /**
     * Get database connection for tenant analytics.
     */
    protected function connection(): string
    {
        return 'tenant';
    }

    /**
     * Apply period range to query builder.
     */
    protected function applyPeriodRange($query, string $column = 'created_at')
    {
        return $query->whereBetween($column, [
            $this->period->from,
            $this->period->to,
        ]);
    }

    /**
     * Apply previous period range to query builder.
     */
    protected function applyPreviousPeriodRange($query, string $column = 'created_at')
    {
        return $query->whereBetween($column, [
            $this->period->previousFrom,
            $this->period->previousTo,
        ]);
    }

    /**
     * Format trend data for response.
     */
    protected function formatTrend(array $labels, array $values): array
    {
        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * Get meta data for response.
     */
    protected function meta(): array
    {
        return array_merge($this->period->toArray(), [
            'interval' => $this->interval,
        ]);
    }

    public function getMeta(): array
    {
        return $this->meta();
    }

    /**
     * Get postgres date trunc format for current interval.
     */
    protected function getPostgresDateFormat(): string
    {
        return AnalyticsInterval::getPostgresFormat($this->interval);
    }

    protected function dateTruncFormat(): string
    {
        return $this->getPostgresDateFormat();
    }

}