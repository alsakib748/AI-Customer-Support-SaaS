<?php

namespace Tests\Unit\Health;

use Tests\TestCase;

class DatabaseTimezoneConfigTest extends TestCase
{
    public function test_postgres_connections_use_the_application_timezone(): void
    {
        $appTimezone = config('app.timezone');

        foreach (['pgsql', 'central', 'tenant'] as $connection) {
            $this->assertSame(
                $appTimezone,
                config("database.connections.{$connection}.timezone"),
                "The '{$connection}' connection must pin its session timezone to the app timezone.",
            );
        }
    }
}
