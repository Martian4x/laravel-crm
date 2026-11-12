<?php

/**
 * AzimioCustomers — Text Messaging (SMS) module configuration.
 *
 * Values here are platform-wide defaults. Anything a tenant may override
 * lives in tenant-scoped storage instead (sms_sender_profiles for provider
 * credentials, tenant columns for quiet hours / caps / thresholds) — this
 * file only supplies the fallback when a tenant has set nothing.
 *
 * See AZIMIO_TODO.md Phase 8 for the full specification. Decision ids
 * referenced below (8.0.1 etc.) are the confirmed decisions in 8.0.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Default driver
    |--------------------------------------------------------------------------
    |
    | The driver used when no sender profile names one. "log" writes the
    | message to the Laravel log, charges nothing and contacts no network —
    | it is the correct default for local development and the test suite.
    | Production tenants always resolve a driver from their sender profile.
    |
    */
    'default' => env('AZIMIO_SMS_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    |
    | Per-driver platform configuration. Tenant credentials are NOT stored
    | here — they live encrypted in sms_sender_profiles.credentials (8.0.5).
    | The credentials below are the platform's own AZIMIOCRM account, used
    | for platform-mode sends.
    |
    | NOTE: every Beem endpoint, field name and error code must be verified
    | against the current Beem Africa documentation during 8.3. The URLs
    | below are placeholders to be confirmed, not trusted values.
    |
    */
    'drivers' => [
        'log' => [
            'driver'  => 'log',
            'channel' => env('AZIMIO_SMS_LOG_CHANNEL', 'stack'),
        ],

        'beem' => [
            'driver'     => 'beem',
            'api_key'    => env('BEEM_API_KEY'),
            'secret_key' => env('BEEM_SECRET_KEY'),
            'endpoints'  => [
                'send'             => env('BEEM_SEND_URL', 'https://apisms.beem.africa/v1/send'),
                'balance'          => env('BEEM_BALANCE_URL', 'https://apisms.beem.africa/public/v1/vendors/balance'),
                'delivery_reports' => env('BEEM_DLR_URL', 'https://dlrapi.beem.africa/public/v1/delivery-reports'),
                'sender_names'     => env('BEEM_SENDER_NAMES_URL', 'https://apisms.beem.africa/public/v1/sender-names'),
            ],
            'timeout'            => (int) env('BEEM_TIMEOUT', 15),
            'recipients_per_call' => (int) env('BEEM_RECIPIENTS_PER_CALL', 500),
        ],

        'africastalking' => [
            'driver'   => 'africastalking',
            'username' => env('AT_USERNAME'),
            'api_key'  => env('AT_API_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform sender identity
    |--------------------------------------------------------------------------
    |
    | The sender name tenants send under in platform mode. It must be
    | registered with the mobile operators before it will deliver (8.17).
    |
    */
    'platform' => [
        'sender_name' => env('AZIMIO_SMS_PLATFORM_SENDER', 'AZIMIOCRM'),
        'driver'      => env('AZIMIO_SMS_PLATFORM_DRIVER', 'beem'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Credits (8.0.1, 8.0.2)
    |--------------------------------------------------------------------------
    |
    | One credit == one SMS *segment*, because that is how the provider bills
    | us — a 320-character message to 10 recipients costs 20 credits.
    |
    | Refunds are pre-submission only: a reservation is released when the
    | provider rejects a message before it reaches the network, and is
    | settled as a debit once the provider accepts it. A message that was
    | accepted and then undelivered is never refunded; the operator already
    | charged us for it.
    |
    */
    'credits' => [
        'unit'                  => 'segment',
        'refund_policy'         => 'pre_submission_only',
        'low_balance_threshold' => (int) env('AZIMIO_SMS_LOW_BALANCE', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Segment counting (8.4)
    |--------------------------------------------------------------------------
    |
    | GSM 03.38 fits 160 characters alone, 153 per part once a message has to
    | be split. UCS-2 (any character outside the GSM alphabet) drops to 70 and
    | 67. Swahili uses no diacritics, so Swahili bodies stay on GSM-7 and cost
    | stays predictable.
    |
    */
    'segments' => [
        'gsm7' => ['single' => 160, 'multipart' => 153],
        'ucs2' => ['single' => 70,  'multipart' => 67],
        'max_segments_per_message' => (int) env('AZIMIO_SMS_MAX_SEGMENTS', 6),
    ],

    /*
    |--------------------------------------------------------------------------
    | Phone normalization (8.4)
    |--------------------------------------------------------------------------
    |
    | Local numbers are normalized to E.164 before anything is sent or
    | charged. Tanzania is the default market; the region is used to resolve
    | bare local numbers such as 07XXXXXXXX.
    |
    */
    'phone' => [
        'default_region'      => env('AZIMIO_SMS_REGION', 'TZ'),
        'default_prefix'      => env('AZIMIO_SMS_PREFIX', '+255'),
        'national_number_len' => 9,
    ],

    /*
    |--------------------------------------------------------------------------
    | Compliance defaults (8.12)
    |--------------------------------------------------------------------------
    |
    | Quiet hours defer marketing-category messages to the next open window;
    | transactional messages always pass through. Per-tenant overrides land
    | in 8.12 — these are only the fallback.
    |
    | The window and the daily cap are placeholders pending confirmation of
    | the actual TCRA / operator rules for bulk messaging in Tanzania.
    |
    */
    'compliance' => [
        'quiet_hours' => [
            'enabled'  => (bool) env('AZIMIO_SMS_QUIET_HOURS', true),
            'start'    => env('AZIMIO_SMS_QUIET_START', '21:00'),
            'end'      => env('AZIMIO_SMS_QUIET_END', '08:00'),
            'timezone' => env('AZIMIO_SMS_TIMEZONE', 'Africa/Dar_es_Salaam'),
            'exempt_categories' => ['transactional'],
        ],

        'daily_send_cap' => (int) env('AZIMIO_SMS_DAILY_CAP', 5000),

        'append_opt_out_notice_to' => ['marketing'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch sending (8.5, 8.8)
    |--------------------------------------------------------------------------
    */
    'batch' => [
        'chunk_size'               => (int) env('AZIMIO_SMS_CHUNK', 200),
        'messages_per_minute'      => (int) env('AZIMIO_SMS_RATE', 600),
        'queue'                    => env('AZIMIO_SMS_QUEUE', 'default'),
        'max_recipients_per_batch' => (int) env('AZIMIO_SMS_MAX_RECIPIENTS', 50000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks (8.19)
    |--------------------------------------------------------------------------
    |
    | Delivery-report and inbound callbacks are public routes, so they are
    | signature-verified and rate-limited. Tenant context is resolved from
    | the sender profile the message was sent under — never from the request
    | body, which an attacker controls.
    |
    */
    'webhooks' => [
        'enabled'            => (bool) env('AZIMIO_SMS_WEBHOOKS', true),
        'signing_secret'     => env('AZIMIO_SMS_WEBHOOK_SECRET'),
        'rate_limit'         => env('AZIMIO_SMS_WEBHOOK_RATE', '120,1'),
        'retain_events_days' => (int) env('AZIMIO_SMS_EVENT_RETENTION', 90),
    ],
];
