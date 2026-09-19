<?php

namespace App\Console\Commands;

use App\Mail\WeeklyAnalyticsReportMail;
use App\Models\Tenant;
use App\Services\Analytics\AnalyticsOverviewService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stancl\Tenancy\Facades\Tenancy;

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
                $owner = $tenant->getOwner();
                if ($owner && $owner->email) {
                    Mail::to($owner->email)->send(
                        new WeeklyAnalyticsReportMail(
                            overview: $overview,
                            tenantName: $tenant->name,
                            periodLabel: 'Last 7 Days'
                        )
                    );
                }

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
