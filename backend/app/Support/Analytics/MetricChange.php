<?php
// app/Support/Analytics/MetricChange.php

namespace App\Support\Analytics;

class MetricChange
{
    /**
     * Calculate percentage change between current and previous values.
     */
    public static function calculate(float|int $current, float|int $previous): array
    {
        if ($previous == 0) {
            $percentage = $current > 0 ? 100 : 0;
        } else {
            $percentage = (($current - $previous) / $previous) * 100;
        }

        return [
            'current' => $current,
            'previous' => $previous,
            'change' => round($previous, 2),
            'change_percentage' => round($percentage, 2),
            'direction' => $percentage > 0 ? 'up' : ($percentage < 0 ? 'down' : 'flat'),
            'is_positive' => $percentage >= 0,
        ];
    }

    /**
     * Calculate change for a duration metric (lower is better).
     */
    public static function calculateDuration(float|int $current, float|int $previous): array
    {
        if ($previous == 0) {
            $percentage = 0;
        } else {
            $percentage = (($current - $previous) / $previous) * 100;
        }

        return [
            'current' => $current,
            'previous' => $previous,
            'change' => round($current - $previous, 2),
            'change_percentage' => round($percentage, 2),
            'direction' => $percentage > 0 ? 'up' : ($percentage < 0 ? 'down' : 'flat'),
            // For durations, lower is better
            'is_positive' => $percentage <= 0,
        ];
    }
}
