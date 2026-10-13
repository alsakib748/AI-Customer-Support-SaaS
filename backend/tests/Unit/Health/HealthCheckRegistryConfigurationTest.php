<?php

namespace Tests\Unit\Health;

use App\Health\Contracts\HealthCheck;
use Tests\TestCase;

/**
 * Guards the health check registration contract: every class listed in
 * config('health.check_classes') must exist, implement the contract and have a
 * matching config('health.checks.<key>') entry.
 *
 * A violation here means the scheduled health:check command throws while
 * resolving the registry, which silently disables ALL monitoring.
 */
class HealthCheckRegistryConfigurationTest extends TestCase
{
    public function test_every_configured_check_class_exists_and_implements_the_contract(): void
    {
        $classes = (array) config('health.check_classes');

        $this->assertNotEmpty($classes, 'health.check_classes must not be empty.');

        foreach ($classes as $class) {
            $this->assertTrue(
                class_exists($class),
                "Configured health check class [{$class}] does not exist.",
            );

            $this->assertTrue(
                is_subclass_of($class, HealthCheck::class),
                "Configured health check [{$class}] must implement ".HealthCheck::class.'.',
            );
        }
    }

    public function test_every_configured_check_has_a_config_entry_for_its_key(): void
    {
        // Note: check keys contain dots (e.g. "application.api"), so they are
        // literal array keys and must not be read through dot-notation.
        $entries = (array) config('health.checks');

        foreach ((array) config('health.check_classes') as $class) {
            $check = $this->app->make($class);
            $key = $check->key();

            $this->assertArrayHasKey(
                $key,
                $entries,
                "No health.checks entry for check key [{$key}] (from {$class}).",
            );

            $this->assertArrayHasKey(
                'enabled',
                (array) $entries[$key],
                "health.checks entry [{$key}] must declare an 'enabled' flag.",
            );
        }
    }
}
