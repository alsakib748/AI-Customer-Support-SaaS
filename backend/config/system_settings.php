<?php

/*
|--------------------------------------------------------------------------
| System Settings Schema
|--------------------------------------------------------------------------
|
| This file is the single source of truth for the platform-wide settings
| allow-list. Every key that may be read or written through the System
| Settings module MUST be declared here. The schema drives:
|
|   - validation rules (FormRequests)
|   - type casting (SystemSettingsSchema)
|   - public/redacted exposure and secret handling
|   - defaults so the platform works before anything is saved
|
| Secrets (credentials) with 'sensitive' => true are encrypted at rest and
| are NEVER returned through the settings API — only a "configured" flag.
|
| Field shape:
|   'group.key' => [
|       'label'     => 'Human label',
|       'type'      => string|text|bool|int|float|array|email|url,
|       'default'   => mixed,
|       'rules'     => array (Laravel validation rules),
|       'public'    => bool   (safe to expose to non-admins),
|       'sensitive' => bool   (encrypted at rest, write-only),
|       'help'      => 'optional helper text',
|       'options'   => array  (optional select options),
|       'min'/'max' => int/float (optional UI hints),
|   ],
|
*/

return [

    // Cache the resolved settings map under this key.
    'cache' => [
        'key' => 'system_settings:all',
        'ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Groups
    |--------------------------------------------------------------------------
    */
    'groups' => [

        'general' => [
            'label'       => 'General',
            'icon'        => 'pi pi-cog',
            'description' => 'Public platform identity, branding and defaults.',
            'fields'      => [
                'platform.name' => [
                    'label'   => 'Platform Name',
                    'type'    => 'string',
                    'default' => 'Support AI',
                    'rules'   => ['required', 'string', 'max:255'],
                    'public'  => true,
                    'help'    => 'Displayed across the platform and outbound emails.',
                ],
                'platform.tagline' => [
                    'label'   => 'Tagline',
                    'type'    => 'string',
                    'default' => '',
                    'rules'   => ['nullable', 'string', 'max:255'],
                    'public'  => true,
                ],
                'platform.logo_path' => [
                    'label'   => 'Logo URL',
                    'type'    => 'url',
                    'default' => '',
                    'rules'   => ['nullable', 'string', 'max:2048'],
                    'public'  => true,
                    'help'    => 'Managed storage path or absolute URL to the platform logo.',
                ],
                'platform.favicon_path' => [
                    'label'   => 'Favicon URL',
                    'type'    => 'url',
                    'default' => '',
                    'rules'   => ['nullable', 'string', 'max:2048'],
                    'public'  => true,
                ],
                'platform.support_email' => [
                    'label'   => 'Support Email',
                    'type'    => 'email',
                    'default' => 'support@example.com',
                    'rules'   => ['required', 'email', 'max:255'],
                    'public'  => true,
                ],
                'platform.support_url' => [
                    'label'   => 'Support URL',
                    'type'    => 'url',
                    'default' => '',
                    'rules'   => ['nullable', 'url', 'max:2048'],
                    'public'  => true,
                ],
                'platform.website_url' => [
                    'label'   => 'Public Website URL',
                    'type'    => 'url',
                    'default' => '',
                    'rules'   => ['nullable', 'url', 'max:2048'],
                    'public'  => true,
                ],
                'platform.default_locale' => [
                    'label'   => 'Default Locale',
                    'type'    => 'string',
                    'default' => 'en',
                    'rules'   => ['required', 'string', 'max:10'],
                    'public'  => true,
                ],
                'platform.default_timezone' => [
                    'label'   => 'Default Timezone',
                    'type'    => 'string',
                    'default' => 'UTC',
                    'rules'   => ['required', 'string', 'max:64'],
                    'public'  => true,
                ],
                'platform.date_format' => [
                    'label'   => 'Date Format',
                    'type'    => 'string',
                    'default' => 'Y-m-d',
                    'rules'   => ['nullable', 'string', 'max:32'],
                    'public'  => true,
                ],
                'platform.time_format' => [
                    'label'   => 'Time Format',
                    'type'    => 'string',
                    'default' => 'H:i',
                    'rules'   => ['nullable', 'string', 'max:32'],
                    'public'  => true,
                ],
            ],
        ],

        'security' => [
            'label'       => 'Security',
            'icon'        => 'pi pi-shield',
            'description' => 'Registration policy and global security defaults.',
            'fields'      => [
                'registration.enabled' => [
                    'label'   => 'Public Registration',
                    'type'    => 'bool',
                    'default' => true,
                    'rules'   => ['boolean'],
                    'public'  => true,
                    'help'    => 'Allow new users to self-register.',
                ],
                'registration.invitation_only' => [
                    'label'   => 'Invitation-only Registration',
                    'type'    => 'bool',
                    'default' => false,
                    'rules'   => ['boolean'],
                    'public'  => true,
                ],
                'security.require_email_verification' => [
                    'label'   => 'Require Email Verification',
                    'type'    => 'bool',
                    'default' => true,
                    'rules'   => ['boolean'],
                    'public'  => false,
                ],
                'security.password_min_length' => [
                    'label'   => 'Minimum Password Length',
                    'type'    => 'int',
                    'default' => 8,
                    'rules'   => ['integer', 'min:6', 'max:128'],
                    'public'  => false,
                    'min'     => 6,
                    'max'     => 128,
                ],
                'security.password_require_mixed_case' => [
                    'label'   => 'Require Mixed Case',
                    'type'    => 'bool',
                    'default' => false,
                    'rules'   => ['boolean'],
                    'public'  => false,
                ],
                'security.password_require_numbers' => [
                    'label'   => 'Require Numbers',
                    'type'    => 'bool',
                    'default' => false,
                    'rules'   => ['boolean'],
                    'public'  => false,
                ],
                'security.password_require_symbols' => [
                    'label'   => 'Require Symbols',
                    'type'    => 'bool',
                    'default' => false,
                    'rules'   => ['boolean'],
                    'public'  => false,
                ],
                'security.session_lifetime_minutes' => [
                    'label'   => 'Session Lifetime (minutes)',
                    'type'    => 'int',
                    'default' => 60,
                    'rules'   => ['integer', 'min:5', 'max:10080'],
                    'public'  => false,
                    'min'     => 5,
                    'max'     => 10080,
                ],
                'security.max_login_attempts' => [
                    'label'   => 'Max Login Attempts',
                    'type'    => 'int',
                    'default' => 5,
                    'rules'   => ['integer', 'min:1', 'max:50'],
                    'public'  => false,
                    'min'     => 1,
                    'max'     => 50,
                ],
                'security.force_https' => [
                    'label'   => 'Force HTTPS',
                    'type'    => 'bool',
                    'default' => false,
                    'rules'   => ['boolean'],
                    'public'  => false,
                    'help'    => 'Reported to the UI; enforcement requires web-server configuration.',
                ],
            ],
        ],

        'ai' => [
            'label'       => 'AI Defaults',
            'icon'        => 'pi pi-sparkles',
            'description' => 'Platform fallback provider, models and global limits.',
            'fields'      => [
                'ai.default_provider' => [
                    'label'   => 'Default Provider',
                    'type'    => 'string',
                    'default' => config('ai.default', 'openai'),
                    'rules'   => ['required', 'string', 'max:50'],
                    'public'  => false,
                    'options' => [], // populated from configured providers at runtime
                ],
                'ai.default_model' => [
                    'label'   => 'Default Model',
                    'type'    => 'string',
                    'default' => '',
                    'rules'   => ['nullable', 'string', 'max:100'],
                    'public'  => false,
                ],
                'ai.default_temperature' => [
                    'label'   => 'Default Temperature',
                    'type'    => 'float',
                    'default' => 0.7,
                    'rules'   => ['numeric', 'min:0', 'max:2'],
                    'public'  => false,
                    'min'     => 0,
                    'max'     => 2,
                ],
                'ai.max_tokens' => [
                    'label'   => 'Max Output Tokens',
                    'type'    => 'int',
                    'default' => 2000,
                    'rules'   => ['integer', 'min:64', 'max:64000'],
                    'public'  => false,
                    'min'     => 64,
                    'max'     => 64000,
                ],
                'ai.allowed_providers' => [
                    'label'   => 'Allowed Providers',
                    'type'    => 'array',
                    'default' => [], // empty = all configured providers allowed
                    'rules'   => ['nullable', 'array'],
                    'public'  => false,
                    'help'    => 'Restrict which AI providers tenants may select. Empty allows all.',
                ],
                'ai.allow_tenant_credentials' => [
                    'label'   => 'Allow Tenant Credentials',
                    'type'    => 'bool',
                    'default' => true,
                    'rules'   => ['boolean'],
                    'public'  => false,
                ],
                'ai.fallback_enabled' => [
                    'label'   => 'Provider Fallback',
                    'type'    => 'bool',
                    'default' => true,
                    'rules'   => ['boolean'],
                    'public'  => false,
                ],
                'ai.fallback_provider' => [
                    'label'   => 'Fallback Provider',
                    'type'    => 'string',
                    'default' => '',
                    'rules'   => ['nullable', 'string', 'max:50'],
                    'public'  => false,
                ],
                'ai.monthly_request_limit' => [
                    'label'   => 'Global Monthly Request Limit',
                    'type'    => 'int',
                    'default' => 0,
                    'rules'   => ['integer', 'min:0'],
                    'public'  => false,
                    'help'    => 'Platform-wide ceiling per tenant. 0 means unlimited.',
                ],
            ],
        ],

        'email' => [
            'label'       => 'Email',
            'icon'        => 'pi pi-envelope',
            'description' => 'Outbound sender identity and delivery configuration.',
            'fields'      => [
                'email.sender_name' => [
                    'label'   => 'Sender Name',
                    'type'    => 'string',
                    'default' => '',
                    'rules'   => ['nullable', 'string', 'max:255'],
                    'public'  => false,
                ],
                'email.sender_email' => [
                    'label'   => 'Sender Email',
                    'type'    => 'email',
                    'default' => '',
                    'rules'   => ['nullable', 'email', 'max:255'],
                    'public'  => false,
                ],
                'email.reply_to_email' => [
                    'label'   => 'Reply-to Email',
                    'type'    => 'email',
                    'default' => '',
                    'rules'   => ['nullable', 'email', 'max:255'],
                    'public'  => false,
                ],
                'email.test_recipient' => [
                    'label'   => 'Default Test Recipient',
                    'type'    => 'email',
                    'default' => '',
                    'rules'   => ['nullable', 'email', 'max:255'],
                    'public'  => false,
                ],
            ],
        ],

        'integrations' => [
            'label'       => 'Integrations',
            'icon'        => 'pi pi-link',
            'description' => 'External provider availability and connection status.',
            'fields'      => [
                'integrations.stripe_enabled' => [
                    'label'   => 'Stripe Enabled',
                    'type'    => 'bool',
                    'default' => true,
                    'rules'   => ['boolean'],
                    'public'  => false,
                ],
                'integrations.paypal_enabled' => [
                    'label'   => 'PayPal Enabled',
                    'type'    => 'bool',
                    'default' => true,
                    'rules'   => ['boolean'],
                    'public'  => false,
                ],
                'integrations.storage_driver' => [
                    'label'   => 'Storage Driver',
                    'type'    => 'string',
                    'default' => 'local',
                    'rules'   => ['required', 'string', 'max:50'],
                    'public'  => false,
                    'options' => [
                        ['label' => 'Local', 'value' => 'local'],
                        ['label' => 'S3', 'value' => 's3'],
                        ['label' => 'Public', 'value' => 'public'],
                    ],
                ],
                'integrations.webhook_signing_secret' => [
                    'label'   => 'Webhook Signing Secret',
                    'type'    => 'string',
                    'default' => '',
                    'rules'   => ['nullable', 'string', 'max:512'],
                    'public'  => false,
                    'sensitive' => true,
                    'help'    => 'Encrypted at rest. Never returned by the API.',
                ],
            ],
        ],

        'operations' => [
            'label'       => 'Operations',
            'icon'        => 'pi pi-server',
            'description' => 'Maintenance controls and operational defaults.',
            'fields'      => [
                'operations.maintenance_enabled' => [
                    'label'   => 'Maintenance Mode',
                    'type'    => 'bool',
                    'default' => false,
                    'rules'   => ['boolean'],
                    'public'  => true,
                    'help'    => 'Blocks tenant and widget traffic. Platform admins are always allowed.',
                ],
                'operations.maintenance_message' => [
                    'label'   => 'Maintenance Message',
                    'type'    => 'text',
                    'default' => 'We are performing scheduled maintenance and will be back shortly.',
                    'rules'   => ['nullable', 'string', 'max:1000'],
                    'public'  => true,
                ],
                'operations.allow_registration_during_maintenance' => [
                    'label'   => 'Allow Registration During Maintenance',
                    'type'    => 'bool',
                    'default' => false,
                    'rules'   => ['boolean'],
                    'public'  => false,
                ],
                'operations.allow_admin_during_maintenance' => [
                    'label'   => 'Allow Platform Admins During Maintenance',
                    'type'    => 'bool',
                    'default' => true,
                    'rules'   => ['boolean'],
                    'public'  => false,
                    'help'    => 'Keep this on to preserve your recovery path.',
                ],
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations Catalog
    |--------------------------------------------------------------------------
    |
    | Providers surfaced on the Integrations page. "probe" is the strategy used
    | by IntegrationStatusService to test the connection ("live" or
    | "configuration"). Secrets/credentials themselves live in the environment.
    |
    */
    'integrations_catalog' => [
        'stripe' => [
            'label'   => 'Stripe',
            'driver'  => 'stripe',
            'probe'   => 'live',
            'docs'    => 'https://stripe.com/docs',
        ],
        'paypal' => [
            'label'   => 'PayPal',
            'driver'  => 'paypal',
            'probe'   => 'live',
            'docs'    => 'https://developer.paypal.com/docs',
        ],
        'email' => [
            'label'   => 'Email (SMTP)',
            'driver'  => 'mail',
            'probe'   => 'configuration',
            'docs'    => 'https://laravel.com/docs/mail',
        ],
        'storage' => [
            'label'   => 'Object Storage',
            'driver'  => 'filesystem',
            'probe'   => 'live',
            'docs'    => 'https://laravel.com/docs/filesystem',
        ],
        'ai' => [
            'label'   => 'AI Provider',
            'driver'  => 'ai',
            'probe'   => 'configuration',
            'docs'    => 'https://laravel.com/docs',
        ],
    ],

];
