<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make("Illuminate\Contracts\Console\Kernel");
$kernel->bootstrap();
$latest = app(App\Health\Services\HealthCheckStorage::class)->allLatest();
var_dump(array_keys($latest));

