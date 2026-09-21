<?php
namespace App\Console\Commands\Billing;

use App\Models\Plan;
use App\Models\PlanProviderPrice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Stripe\StripeClient;

class SeedStripeProductCommand extends Command
{
    protected $signature = 'billing:seed-stripe-product
                            {plan : Plan slug}
                            {--cycle=monthly : monthly|yearly}';

    protected $description = 'Create a Stripe Product + Price for a plan and save the price ID';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $slug  = $this->argument('plan');
        $cycle = $this->option('cycle');

        $plan = Plan::where('slug', $slug)->first();

        if (! $plan) {
            $this->error("Plan not found: {$slug}");
            return self::FAILURE;
        }

        $client = new StripeClient(config('payment.providers.stripe.secret'));

        $this->info("Creating Stripe product for plan: {$plan->name}");

        // Reuse product if we already created one
        $existing = PlanProviderPrice::where('plan_id', $plan->id)
            ->where('provider', 'stripe')
            ->where('billing_cycle', $cycle)
            ->first();

        if ($existing) {
            $this->warn("Stripe price already exists: {$existing->provider_price_id}");

            return self::SUCCESS;
        }

        $product = $client->products->create([
            'name'        => $plan->name,
            'description' => $plan->description,
            'metadata'    => ['plan_id' => $plan->id, 'slug' => $plan->slug],
        ]);

        $amount = $cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;

        $price = $client->prices->create([
            'product'     => $product->id,
            'unit_amount' => (int) round((float) $amount * 100),
            'currency'    => strtolower($plan->currency ?? 'usd'),
            'recurring'   => ['interval' => $cycle === 'yearly' ? 'year' : 'month'],
            'metadata'    => ['plan_id' => $plan->id],
        ]);

        PlanProviderPrice::updateOrCreate(
            [
                'plan_id'       => $plan->id,
                'provider'      => 'stripe',
                'billing_cycle' => $cycle,
                'currency'      => $plan->currency ?? 'USD',
            ],
            [
                'provider_price_id' => $price->id,
                'amount'            => $amount,
                'is_active'         => true,
            ]
        );

        $this->info("✓ Stripe product: {$product->id}");
        $this->info("✓ Stripe price:   {$price->id}");
        $this->info("Saved to plan_provider_prices");

        return self::SUCCESS;
    }

}
