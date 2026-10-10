<?php

namespace App\Services\SystemSettings;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Read-only operational diagnostics for the Operations page.
 *
 * Every check degrades gracefully: a failing probe reports "error" instead of
 * throwing, and no secrets are ever included in the output.
 */
class SystemSettingsHealthService
{
    public function __construct(
        protected SystemSettingsService $settings,
        protected IntegrationStatusService $integrations,
    ) {
    }

    public function checks(): array
    {
        return [
            'application' => $this->applicationCheck(),
            'database'    => $this->databaseCheck(),
            'cache'       => $this->cacheCheck(),
            'queue'       => $this->queueCheck(),
            'storage'     => $this->storageCheck(),
            'mail'        => $this->mailCheck(),
            'ai'          => $this->aiCheck(),
            'maintenance' => $this->maintenanceCheck(),
        ];
    }

    public function summary(): array
    {
        $checks = $this->checks();
        $counts = ['ok' => 0, 'warning' => 0, 'error' => 0];

        foreach ($checks as $check) {
            $status = $check['status'] ?? 'warning';
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        $overall = $counts['error'] > 0 ? 'error' : ($counts['warning'] > 0 ? 'warning' : 'ok');

        return ['overall' => $overall, 'counts' => $counts, 'checks' => $checks];
    }

    protected function check(string $label, string $status, string $summary, array $details = []): array
    {
        return compact('label', 'status', 'summary', 'details');
    }

    protected function applicationCheck(): array
    {
        return $this->check(
            'Application',
            config('app.debug') ? 'warning' : 'ok',
            'Version ' . app()->version() . ' · PHP ' . PHP_VERSION,
            [
                'environment' => config('app.env'),
                'debug'       => (bool) config('app.debug'),
                'url'         => config('app.url'),
            ]
        );
    }

    protected function databaseCheck(): array
    {
        $latency = $this->integrations->databaseLatencyMs();

        if ($latency === null) {
            return $this->check('Database', 'error', 'Central database is unreachable.');
        }

        $details = ['driver' => DB::connection('central')->getDriverName(), 'latency_ms' => $latency];

        try {
            $details['tenants'] = DB::connection('central')->table('tenants')->count();
            $details['users']   = DB::connection('central')->table('users')->count();
        } catch (\Throwable $e) {
            // Optional counts — ignore if tables are absent.
        }

        return $this->check('Database', 'ok', sprintf('Central connection healthy (%s ms).', $latency), $details);
    }

    protected function cacheCheck(): array
    {
        $store = (string) config('cache.default');
        $key   = 'system_settings:health:' . Str::random(8);

        try {
            Cache::put($key, 'ok', now()->addSeconds(10));
            $value = Cache::get($key);
            Cache::forget($key);

            if ($value !== 'ok') {
                return $this->check('Cache', 'warning', 'Cache store did not return the written value.', ['store' => $store]);
            }

            return $this->check('Cache', 'ok', 'Cache read/write healthy.', ['store' => $store]);
        } catch (\Throwable $e) {
            return $this->check('Cache', 'error', 'Cache store unavailable.', ['store' => $store]);
        }
    }

    protected function queueCheck(): array
    {
        $connection = (string) config('queue.default');
        $pending    = null;
        $failed     = null;

        try {
            if (in_array($connection, ['database', 'central'], true)) {
                $pending = DB::connection('central')->table('jobs')->count();
                $failed  = DB::connection('central')->table('failed_jobs')->count();
            }
        } catch (\Throwable $e) {
            // Tables may not exist — leave nulls.
        }

        $status = ($failed !== null && $failed > 0) ? 'warning' : 'ok';

        return $this->check('Queue', $status, 'Driver: ' . $connection, array_filter([
            'connection'   => $connection,
            'pending_jobs' => $pending,
            'failed_jobs'  => $failed,
        ], fn ($v) => ! is_null($v)));
    }

    protected function storageCheck(): array
    {
        $disk = (string) config('filesystems.default');

        try {
            $path = 'system-settings/health-' . Str::random(8) . '.txt';
            Storage::disk($disk)->put($path, 'ok');
            $ok = Storage::disk($disk)->get($path) === 'ok';
            Storage::disk($disk)->delete($path);

            return $this->check(
                'Storage',
                $ok ? 'ok' : 'warning',
                $ok ? 'Default disk is writable.' : 'Disk round-trip mismatch.',
                ['disk' => $disk]
            );
        } catch (\Throwable $e) {
            return $this->check('Storage', 'error', 'Default disk is not writable.', ['disk' => $disk]);
        }
    }

    protected function mailCheck(): array
    {
        $mailer = (string) config('mail.default');
        $from   = (string) config('mail.from.address');
        $ok     = $mailer !== '' && $from !== '';

        return $this->check(
            'Mail',
            $ok ? 'ok' : 'warning',
            $ok ? 'Mail transport configured.' : 'Mail transport incomplete.',
            ['mailer' => $mailer, 'from' => $from]
        );
    }

    protected function aiCheck(): array
    {
        $status = $this->integrations->test('ai');
        $enabled = (bool) config('ai.features.enabled', true);

        return $this->check(
            'AI',
            $status['success'] ? 'ok' : 'warning',
            $status['message'],
            ['enabled' => $enabled]
        );
    }

    protected function maintenanceCheck(): array
    {
        $enabled = $this->settings->isMaintenanceEnabled();

        return $this->check(
            'Maintenance Mode',
            $enabled ? 'warning' : 'ok',
            $enabled ? 'Maintenance mode is ACTIVE.' : 'Platform is operating normally.',
            ['enabled' => $enabled]
        );
    }
}
