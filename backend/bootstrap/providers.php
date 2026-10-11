<?php

use App\Providers\AppServiceProvider;
use App\Providers\BillingServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\PaymentServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    BillingServiceProvider::class,
    EventServiceProvider::class,
    PaymentServiceProvider::class,
    TenancyServiceProvider::class,
];
