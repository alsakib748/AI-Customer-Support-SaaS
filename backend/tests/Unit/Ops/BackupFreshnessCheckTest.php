<?php

namespace Tests\Unit\Ops;

use App\Health\Checks\BackupFreshnessCheck;
use App\Health\Enums\HealthStatus;
use Tests\TestCase;

class BackupFreshnessCheckTest extends TestCase
{
    /** @var string[] */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    private function configure(?string $markerPath, int $maxAgeHours = 26): void
    {
        config([
            'health.checks' => [
                'backup.freshness' => [
                    'label' => 'Backup Freshness',
                    'critical' => false,
                    'enabled' => true,
                    'marker_path' => $markerPath,
                    'max_age_hours' => $maxAgeHours,
                ],
            ],
        ]);
    }

    private function markerAgedHours(float $hours): string
    {
        $path = tempnam(sys_get_temp_dir(), 'backup-marker-');
        touch($path, (int) (time() - $hours * 3600));
        clearstatcache(true, $path);
        $this->files[] = $path;

        return $path;
    }

    private function statusFor(string $markerPath = '', int $maxAgeHours = 26): HealthStatus
    {
        $this->configure($markerPath, $maxAgeHours);

        return (new BackupFreshnessCheck)->run()->status;
    }

    public function test_disabled_when_no_marker_is_configured(): void
    {
        $this->assertSame(HealthStatus::Disabled, $this->statusFor(''));
    }

    public function test_unhealthy_when_marker_file_is_missing(): void
    {
        $this->assertSame(HealthStatus::Unhealthy, $this->statusFor('C:/path/does/not/exist/.last_backup'));
    }

    public function test_healthy_when_marker_is_fresh(): void
    {
        $this->assertSame(HealthStatus::Healthy, $this->statusFor($this->markerAgedHours(1)));
    }

    public function test_degraded_when_marker_approaches_max_age(): void
    {
        // max 10h, age 9h -> beyond 80% but not beyond the maximum.
        $this->assertSame(HealthStatus::Degraded, $this->statusFor($this->markerAgedHours(9), 10));
    }

    public function test_unhealthy_when_marker_exceeds_max_age(): void
    {
        $this->assertSame(HealthStatus::Unhealthy, $this->statusFor($this->markerAgedHours(30), 26));
    }
}
