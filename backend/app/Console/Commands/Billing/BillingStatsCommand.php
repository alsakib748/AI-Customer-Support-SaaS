<?php

namespace App\Console\Commands\Billing;

use App\Services\Billing\BillingAnalyticsService;
use Illuminate\Console\Command;

class BillingStatsCommand extends Command
{
    protected $signature = 'billing:stats {--detailed : Show detailed breakdown}';
    protected $description = 'Show platform billing statistics';
    /**
     * Execute the console command.
     */
    public function handle(BillingAnalyticsService $analytics): int
    {
        // $stats = $analytics->getPlatformMetrics();
        try {
            $stats = $analytics->getPlatformMetrics();
        } catch (\Throwable $e) {
            $this->error('Failed to compute stats: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Platform Billing Statistics');
        $this->line('');

        $this->table(
            ['Metric', 'Value'],
            [
                ['MRR',             '$' . number_format($stats['mrr'], 2)],
                ['ARR',             '$' . number_format($stats['arr'], 2)],
                ['Active Subs',     $stats['active_subscriptions']],
                ['Trialing',        $stats['trialing_tenants']],
                ['Past Due',        $stats['past_due']],
                ['Cancelled',       $stats['cancelled']],
                ['Expired',         $stats['expired']],
                ['New (30d)',       $stats['new_subscriptions']],
                ['Churned (30d)',   $stats['churned_subscriptions']],
                ['Churn Rate',      $stats['churn_rate'] . '%'],
                ['Revenue (30d)',   '$' . number_format($stats['revenue_last_30_days'], 2)],
                ['Failed Payments', $stats['payment_failures_last_30_days']],
            ]
        );

        if ($this->option('detailed') && !empty($stats['plan_distribution'])) {
            $this->line('');
            $this->info('Plan Distribution:');
            $this->table(
                ['Plan', 'Subscribers'],
                collect($stats['plan_distribution'])
                    ->map(fn ($p) => [$p['plan_name'], $p['count']])
                    ->toArray()
            );
        }

        return self::SUCCESS;
    }
}
