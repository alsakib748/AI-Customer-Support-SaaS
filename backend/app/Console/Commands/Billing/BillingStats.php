<?php

namespace App\Console\Commands\Billing;

use App\Services\Billing\InvoiceService;
use App\Services\Billing\PaymentService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:billing-stats')]
#[Description('Command description')]
class BillingStats extends Command
{
    protected $signature = 'billing:stats 
                            {--tenant= : Filter by tenant}
                            {--detailed : Show detailed breakdown}';

    protected $description = 'Display billing statistics';
    /**
     * Execute the console command.
     */
    public function handle(
        SubscriptionService $subscriptionService,
        InvoiceService $invoiceService,
        PaymentService $paymentService
    ): int {
        $this->info('📊 Billing Statistics');
        $this->line('════════════════════════════════════════');

        if ($this->option('tenant')) {
            $this->showTenantStats(
                $this->option('tenant'),
                $invoiceService,
                $paymentService
            );
        } else {
            $this->showGlobalStats($subscriptionService);
        }

        return self::SUCCESS;
    }

    protected function showGlobalStats(SubscriptionService $service): void
    {
        $stats = $service->getStatistics();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Subscriptions', $stats['total']],
                ['Active', $stats['active']],
                ['Trialing', $stats['trialing']],
                ['Past Due', $stats['past_due']],
                ['Cancelled', $stats['cancelled']],
                ['Expired', $stats['expired']],
            ]
        );

        if ($this->option('detailed')) {
            $this->line('');
            $this->info('By Plan:');
            $this->table(
                ['Plan', 'Count'],
                array_map(
                    fn($name, $count) => [$name, $count],
                    array_keys($stats['by_plan']),
                    array_values($stats['by_plan'])
                )
            );

            $this->line('');
            $this->info('By Billing Cycle:');
            $this->table(
                ['Cycle', 'Count'],
                array_map(
                    fn($name, $count) => [ucfirst($name), $count],
                    array_keys($stats['by_cycle']),
                    array_values($stats['by_cycle'])
                )
            );
        }
    }

    protected function showTenantStats(
        string $tenantId,
        InvoiceService $invoiceService,
        PaymentService $paymentService
    ): void {
        $invoiceStats = $invoiceService->getStatistics($tenantId);
        $paymentStats = $paymentService->getStatistics($tenantId);

        $this->info('Invoices:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Invoices', $invoiceStats['total']],
                ['Paid', $invoiceStats['paid']],
                ['Open', $invoiceStats['open']],
                ['Overdue', $invoiceStats['overdue']],
                ['Total Paid', '$' . number_format($invoiceStats['total_amount'], 2)],
                ['Outstanding', '$' . number_format($invoiceStats['outstanding_amount'], 2)],
            ]
        );

        $this->line('');
        $this->info('Payments:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Payments', $paymentStats['total']],
                ['Completed', $paymentStats['completed']],
                ['Failed', $paymentStats['failed']],
                ['Total Revenue', '$' . number_format($paymentStats['total_revenue'], 2)],
                ['Total Refunded', '$' . number_format($paymentStats['total_refunded'], 2)],
            ]
        );
    }
}