<?php

return [
    'default' => env('PAYMENT_PROVIDER', 'stripe'),

    'providers' => [
        'stripe' => [
            'secret'          => env('STRIPE_SECRET'),
            'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
            'webhook_secret'  => env('STRIPE_WEBHOOK_SECRET'),
            'api_version'     => env('STRIPE_API_VERSION', '2024-06-20'),
        ],

        'paypal' => [
            'client_id'     => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'webhook_id'    => env('PAYPAL_WEBHOOK_ID'),
            'mode'          => env('PAYPAL_MODE', 'sandbox'),   // sandbox | live
            'currency'      => env('PAYPAL_CURRENCY', 'USD'),
        ],
    ],

    // URLs the provider redirects to after checkout
    'redirects' => [
        'success' => env('PAYMENT_SUCCESS_URL', env('APP_URL') . '/billing/success'),
        'cancel'  => env('PAYMENT_CANCEL_URL',  env('APP_URL') . '/billing/cancel'),
    ],
];
