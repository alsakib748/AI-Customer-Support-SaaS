<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Tenant\AnalyticsExport;
use App\Services\Analytics\AnalyticsExportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Facades\Tenancy;

#[Signature('app:cleanup-analytics-exports')]
#[Description('Command description')]
class CleanupAnalyticsExports extends Command
{

    protected $signature = 'analytics:cleanup-exports
                            {--days=30 : Delete exports older than this many days}
                            {--dry-run : Show what would be deleted}';

    protected $description = 'Delete old analytics exports and their files';

    /**
     * Execute the console command.
     */
    /**
     * Inject AnalyticsExportService via method parameter.
     */
    public function handle(AnalyticsExportService $service): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $this->info("Cleaning exports older than {$days} days (before {$cutoff})...");

        $totalDeleted = 0;
        $totalFiles = 0;

        // Capture $service in the closure's `use` clause
        Tenant::where('status', 'active')->chunk(
            50,
            function ($tenants) use ($cutoff, $dryRun, $service, &$totalDeleted, &$totalFiles) {
                foreach ($tenants as $tenant) {
                    try {
                        Tenancy::initialize($tenant);

                        $query = AnalyticsExport::where('created_at', '<', $cutoff);
                        $count = (clone $query)->count();

                        if ($dryRun) {
                            $this->line("  [tenant {$tenant->id}] would delete {$count} exports");
                            continue;
                        }

                        foreach ($query->cursor() as $export) {
                            if ($export->file_path) {
                                $service->deleteFile($export);
                                $totalFiles++;
                            }
                            $export->delete();
                            $totalDeleted++;
                        }
                    } catch (\Throwable $e) {
                        Log::error('Export cleanup failed', [
                            'tenant_id' => $tenant->id,
                            'error' => $e->getMessage(),
                        ]);

                        $this->error("  [tenant {$tenant->id}] {$e->getMessage()}");
                    } finally {
                        Tenancy::end();
                    }
                }
            }
        );

        if ($dryRun) {
            $this->warn('Dry run — nothing actually deleted.');
        } else {
            $this->info("Deleted {$totalDeleted} export records and {$totalFiles} files.");
        }

        return self::SUCCESS;
    }
}