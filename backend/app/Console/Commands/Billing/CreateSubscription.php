<?php

namespace App\Console\Commands\Billing;

use App\Models\Coupon;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Billing\SubscriptionService;
use Illuminate\Console\Command;

class CreateSubscription extends Command
{
    protected $signature = 'billing:create-subscription
                            {tenant : Tenant ID}
                            {plan : Plan slug}
                            {--cycle=monthly : Billing cycle (monthly/yearly)}
                            {--coupon= : Coupon code}
                            {--trial : Force trial}';

    protected $description = 'Create a subscription for a tenant';
    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $service)
    {
        $tenant = Tenant::find($this->argument('tenant'));
        if (!$tenant) {
            $this->error('Tenant not found.');
            return self::FAILURE;
        }

        $plan = Plan::where('slug', $this->argument('plan'))->first();
        if (!$plan) {
            $this->error('Plan not found.');
            return self::FAILURE;
        }

        $coupon = null;
        if ($this->option('coupon')) {
            $coupon = \App\Models\Coupon::byCode($this->option('coupon'))->first();
            if (!$coupon) {
                $this->error('Coupon not found or invalid.');
                return self::FAILURE;
            }
        }

        try {
            $subscription = $service->createSubscription(
                $tenant,
                $plan,
                $this->option('cycle'),
                $coupon
            );

            $this->info('✓ Subscription created successfully!');
            $this->table(
                ['Field', 'Value'],
                [
                    ['ID', $subscription->id],
                    ['Tenant', $tenant->name],
                    ['Plan', $plan->name],
                    ['Cycle', $subscription->billing_cycle],
                    ['Status', $subscription->status_label],
                    ['Ends At', $subscription->ends_at?->format('Y-m-d H:i')],
                ]
            );

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
