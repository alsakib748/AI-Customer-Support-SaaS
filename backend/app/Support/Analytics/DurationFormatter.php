<?php
// app/Support/Analytics/DurationFormatter.php

namespace App\Support\Analytics;

class DurationFormatter
{
    /**
     * Format seconds into human-readable format.
     */
    public static function format(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }

        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return $secs > 0 ? "{$minutes}m {$secs}s" : "{$minutes}m";
        }

        if ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
        }

        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        return $hours > 0 ? "{$days}d {$hours}h" : "{$days}d";
    }

    /**
     * Format seconds as array with components.
     */
    public static function toArray(int $seconds): array
    {
        return [
            'seconds' => $seconds,
            'minutes' => round($seconds / 60, 2),
            'hours' => round($seconds / 3600, 2),
            'formatted' => self::format($seconds),
        ];
    }
}