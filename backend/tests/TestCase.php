<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Events\TenantDeleted;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The app ships with a PostgreSQL 'central' connection. Tests point it at
        // an isolated in-memory SQLite database so central model queries
        // (SystemSetting, AuditLog, User, ...) never touch the real database.
        config([
            'database.connections.central.driver'   => 'sqlite',
            'database.connections.central.database' => ':memory:',
        ]);

        DB::purge('central');

        // Stancl: never provision real tenant databases (and never drop them)
        // from tests. Registration tests create central Tenant rows only, which
        // is exactly what the onboarding logic under test touches.
        $this->app->make('events')->forget(TenantCreated::class);
        $this->app->make('events')->forget(TenantDeleted::class);
    }
}
