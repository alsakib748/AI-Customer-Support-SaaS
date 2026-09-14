<?php
// app/Support/Analytics/AnalyticsInterval.php

namespace App\Support\Analytics;

class AnalyticsInterval
{
    public const HOUR = 'hour';
    public const DAY = 'day';
    public const WEEK = 'week';
    public const MONTH = 'month';

    public static function determine(AnalyticsPeriod $period, ?string $requested = null): string
    {
        // Validate requested interval
        if (
            $requested && in_array($requested, [
                self::HOUR,
                self::DAY,
                self::WEEK,
                self::MONTH
            ])
        ) {
            return $requested;
        }

        $days = $period->getDurationInDays();

        return match (true) {
            $days <= 2 => self::HOUR,
            $days <= 90 => self::DAY,
            $days <= 365 => self::WEEK,
            default => self::MONTH,
        };
    }

    public static function getPostgresFormat(string $interval): string
    {
        return match ($interval) {
            self::HOUR => 'YYYY-MM-DD HH24:00',
            self::DAY => 'YYYY-MM-DD',
            self::WEEK => 'IYYY-IW',
            self::MONTH => 'YYYY-MM',
            default => 'YYYY-MM-DD',
        };
    }

    public static function getDateFormat(string $interval): string
    {
        return match ($interval) {
            self::HOUR => 'M j, H:00',
            self::DAY => 'M j',
            self::WEEK => '\\Wk W, Y',
            self::MONTH => 'M Y',
            default => 'M j',
        };
    }
}