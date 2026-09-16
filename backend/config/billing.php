<?php
// config/billing.php

return [
    /*
    |--------------------------------------------------------------------------
    | Currency & Locale
    |--------------------------------------------------------------------------
    */
    'currency' => env('BILLING_CURRENCY', 'USD'),
    'currency_symbol' => env('BILLING_CURRENCY_SYMBOL', '$'),

    /*
    |--------------------------------------------------------------------------
    | Billing Cycle Options
    |--------------------------------------------------------------------------
    */
    'cycles' => [
        'monthly' => [
            'label' => 'Monthly',
            'months' => 1,
            'discount' => 0,
        ],
        'yearly' => [
            'label' => 'Yearly',
            'months' => 12,
            'discount' => 15, // 15% off
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Settings
    |--------------------------------------------------------------------------
    */
    'invoice' => [
        'prefix' => 'INV-',
        'due_days' => 7,
        'tax_rate' => env('BILLING_TAX_RATE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial Settings
    |--------------------------------------------------------------------------
    */
    'trial' => [
        'default_days' => env('BILLING_TRIAL_DAYS', 14),
        'require_payment_method' => env('BILLING_TRIAL_REQUIRE_PAYMENT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Grace Periods
    |--------------------------------------------------------------------------
    */
    'grace_period' => [
        'past_due_days' => 3, // Days after due before marking past_due
        'suspend_days' => 7,  // Days after due before suspending
        'cancel_days' => 30,  // Days after due before cancelling
    ],

    /*
    |--------------------------------------------------------------------------
    | Reminder Settings
    |--------------------------------------------------------------------------
    */
    'reminders' => [
        'renewal' => [7, 3, 1], // Days before renewal
        'payment_failed' => [1, 3, 7], // Days after failure
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'stripe' => [
            'enabled' => env('STRIPE_ENABLED', true),
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
        'paypal' => [
            'enabled' => env('PAYPAL_ENABLED', false),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'mode' => env('PAYPAL_MODE', 'sandbox'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Plan Features
    |--------------------------------------------------------------------------
    */
    'default_limits' => [
        'ai_messages' => 100,
        'agents' => 1,
        'documents' => 10,
        'storage_bytes' => 104857600, // 100MB
        'conversations' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metered Billing
    |--------------------------------------------------------------------------
    */
    'metered_billing' => [
        'enabled' => env('BILLING_METERED_ENABLED', false),
        'ai_message_price' => env('BILLING_AI_MESSAGE_PRICE', 0.001),
        'conversation_price' => env('BILLING_CONVERSATION_PRICE', 0.01),
    ],
];