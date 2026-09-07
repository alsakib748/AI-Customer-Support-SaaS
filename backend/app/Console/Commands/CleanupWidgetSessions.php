<?php

namespace App\Console\Commands;

use App\Services\ChatWidget\WidgetSessionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:cleanup-widget-sessions')]
#[Description('Command description')]
class CleanupWidgetSessions extends Command
{
    protected $signature = 'widget:cleanup-sessions
                            {--days=30 : Days to keep sessions (default: 30)}
                            {--soft : Soft cleanup - mark as expired instead of deleting}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--stats : Show statistics without cleaning}';

    protected $description = 'Clean up expired widget sessions';

    protected WidgetSessionService $sessionService;

    public function __construct(WidgetSessionService $sessionService)
    {
        parent::__construct();
        $this->sessionService = $sessionService;
    }

    public function handle()
    {
        // Show statistics mode
        if ($this->option('stats')) {
            $this->showStats();
            return 0;
        }

        $days = (int) $this->option('days');
        $soft = $this->option('soft');
        $dryRun = $this->option('dry-run');

        $this->info('🧹 Widget Session Cleanup');
        $this->line('─────────────────────────────');
        $this->line("Retention period: {$days} days");
        $this->line("Mode: " . ($soft ? 'Soft (mark expired)' : 'Hard (delete)'));
        $this->line("Dry run: " . ($dryRun ? 'Yes' : 'No'));
        $this->line('');

        // Show current stats
        $this->showStats();

        if ($dryRun) {
            $this->warn('⚠️  Dry run mode - no actual deletions will be performed');
            return 0;
        }

        if ($soft) {
            $this->info('🔄 Performing soft cleanup...');
            $results = $this->sessionService->softCleanupExpiredSessions();
        } else {
            $this->info('🗑️  Performing hard cleanup...');
            $results = $this->sessionService->cleanupExpiredSessions();
        }

        $this->line('');
        $this->info('✅ Cleanup completed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Sessions deleted/updated', $results['deleted'] ?? $results['updated'] ?? 0],
                ['Details', json_encode($results['details'] ?? [])],
            ]
        );

        $this->line('');
        $this->showStats();

        Log::info('Widget session cleanup completed', $results);

        return 0;
    }

    protected function showStats(): void
    {
        $stats = $this->sessionService->getCleanupStats();

        $this->line('📊 Current Session Statistics');
        $this->line('─────────────────────────────');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Sessions', $stats['total_sessions']],
                ['Active (last 30 min)', $stats['active']],
                ['Inactive (1+ day)', $stats['inactive_1_day']],
                ['Inactive (7+ days)', $stats['inactive_7_days']],
                ['Inactive (30+ days)', $stats['inactive_30_days']],
                ['No Customer', $stats['no_customer']],
                ['No Conversation', $stats['no_conversation']],
            ]
        );
    }
}