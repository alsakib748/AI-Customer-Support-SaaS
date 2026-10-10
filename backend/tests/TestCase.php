<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

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
    }
}
