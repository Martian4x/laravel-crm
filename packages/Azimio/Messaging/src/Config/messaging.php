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
    | VERIFIED against docs.beem.africa on 2026-11-12 (their OpenAPI spec at
    | /openapi/sms.json plus the SMS guides). All four Beem URLs below are
    | confirmed, as is HTTP Basic auth with the api key as username.
    |
    | Still NOT documented by Beem, and therefore not assumed anywhere in
    | this module: how they count segments or charge for long and Unicode
    | messages on /v1/send. Our GSM-7 figures below come from their
    | Multicountry product, a different API. Charges must be reconciled
    | against real invoices before anyone bills on them (see 8.0.1).
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
            'timeout'             => (int) env('BEEM_TIMEOUT', 15),

            // Beem documents a hard maximum of 1000 recipients per request
            // (their error code 114). 500 is a deliberately conservative
            // default; BeemDriver clamps anything above 1000 regardless.
            'recipients_per_call' => (int) env('BEEM_RECIPIENTS_PER_CALL', 500),

            // Beem's encoding field is an INTEGER, and 0 ("default GSM") is
            // the only value their /v1/send reference documents.
            'encoding'            => (int) env('BEEM_ENCODING', 0),

            // Their docs ask callers to wait at least five minutes after
            // submitting before checking delivery status.
            'dlr_min_age_minutes' => (int) env('BEEM_DLR_MIN_AGE', 5),
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
        'gsm7'                     => ['single' => 160, 'multipart' => 153],
        'ucs2'                     => ['single' => 70,  'multipart' => 67],
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
            'enabled'           => (bool) env('AZIMIO_SMS_QUIET_HOURS', true),
            'start'             => env('AZIMIO_SMS_QUIET_START', '21:00'),
            'end'               => env('AZIMIO_SMS_QUIET_END', '08:00'),
            'timezone'          => env('AZIMIO_SMS_TIMEZONE', 'Africa/Dar_es_Salaam'),
            'exempt_categories' => ['transactional'],
        ],

        // Platform fallback; a tenant may set its own in settings (8.12).
        // 0 means uncapped.
        'daily_send_cap' => (int) env('AZIMIO_SMS_DAILY_CAP', 5000),

        'append_opt_out_notice_to' => ['marketing'],

        // Appended to marketing bodies BEFORE counting, so the quoted price
        // includes it. Leave null to use the translated default.
        'opt_out_notice' => env('AZIMIO_SMS_OPT_OUT_NOTICE'),
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
    | Activity timeline (8.10)
    |--------------------------------------------------------------------------
    |
    | Sent messages appear on the person/lead timeline, but only for small
    | sends. A 10,000-recipient campaign would write 10,000 timeline entries
    | and bury the real conversation on every contact it touched; a campaign
    | belongs in the batch history instead.
    |
    */
    'activity' => [
        'timeline_max_recipients' => (int) env('AZIMIO_SMS_TIMELINE_MAX', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery reports (8.19)
    |--------------------------------------------------------------------------
    |
    | CORRECTION to the original 8.19 plan, from reading Beem's docs: their
    | REST SMS API has NO delivery-report callback. Status is obtained by
    | POLLING GET /public/v1/delivery-reports with (request_id, dest_addr),
    | one request per recipient, and they ask for a five-minute wait after
    | submitting. So delivery reporting is a scheduled command, not a
    | webhook, and no signature question arises for it at all.
    |
    */
    'delivery_reports' => [
        'enabled'        => (bool) env('AZIMIO_SMS_DLR_POLL', true),
        'batch_size'     => (int) env('AZIMIO_SMS_DLR_BATCH', 200),
        'max_age_hours'  => (int) env('AZIMIO_SMS_DLR_MAX_AGE', 48),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbound webhooks (8.19)
    |--------------------------------------------------------------------------
    |
    | Inbound/two-way SMS IS a callback, but it is a separate Beem product
    | needing a two-way number, and their docs define NO signature or HMAC
    | scheme for it — only an optional, unspecified "validate the API key".
    | Treat an inbound callback as untrusted input: it may not be from Beem.
    |
    | Until Beem documents a real verification method, the route is kept
    | DISABLED by default, and enabling it should mean an unguessable path
    | plus whatever network restriction the deployment can manage. Tenant
    | context is resolved from the sender profile the number belongs to,
    | never from the request body.
    |
    */
    'webhooks' => [
        'enabled'            => (bool) env('AZIMIO_SMS_WEBHOOKS', false),
        'shared_secret'      => env('AZIMIO_SMS_WEBHOOK_SECRET'),
        'rate_limit'         => env('AZIMIO_SMS_WEBHOOK_RATE', '120,1'),
        'retain_events_days' => (int) env('AZIMIO_SMS_EVENT_RETENTION', 90),
    ],
];
