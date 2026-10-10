<?php

namespace App\Health\Checks;

use App\Health\DTOs\HealthCheckResult;
use App\Health\Enums\HealthStatus;
use Illuminate\Contracts\Foundation\Application;

class ApplicationHealthCheck extends AbstractHealthCheck
{
    public function __construct(protected Application $app)
    {
    }

    public function key(): string
    {
        return 'application.api';
    }

    protected function perform(): HealthCheckResult
    {
        return HealthCheckResult::make(
            $this->key(),
            HealthStatus::Healthy,
            'API is up and responding',
            [
                'version'  => $this->app->version(),
                'php'      => PHP_VERSION,
                'env'      => $this->app->environment(),
                'url'      => (string) config('app.url'),
                'debug'    => (bool) config('app.debug'),
                'timezone' => (string) config('app.timezone'),
            ],
        );
    }
}