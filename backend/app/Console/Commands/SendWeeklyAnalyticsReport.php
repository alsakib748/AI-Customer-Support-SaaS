<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Analytics\AnalyticsOverviewService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Facades\Tenancy;

#[Signature('app:send-weekly-analytics-report')]
#[Description('Command description')]
class SendWeeklyAnalyticsReport extends Command
{
    protected $signature = 'analytics:weekly-report';
    protected $description = 'Send weekly analytics reports to tenant owners';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenants = Tenant::where('status', 'active')->get();

        foreach ($tenants as $tenant) {
            try {
                Tenancy::initialize($tenant);

                $overview = (new AnalyticsOverviewService())
                    ->withFilters(['period' => '7d'])
                    ->overview();

                // TODO: Mail a Mailable view with $overview
                // Mail::to($tenant->getOwner()?->email)->send(new WeeklyReportMail($tenant, $overview));

                Log::info('Weekly report sent', ['tenant_id' => $tenant->id]);
            } catch (\Throwable $e) {
                Log::error('Weekly report failed', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                Tenancy::end();
            }
        }

        return self::SUCCESS;
    }
}