<?php

return [
    'name' => 'Messaging',

    'sms' => [
        'title' => 'SMS',

        'modes' => [
            'platform' => 'Platform credits (AZIMIOCRM)',
            'own'      => 'Own provider',
        ],

        'status' => [
            'queued'              => 'Queued',
            'sending'             => 'Sending',
            'submitted'           => 'Submitted',
            'delivered'           => 'Delivered',
            'failed'              => 'Failed',
            'failed-own-provider' => 'Failed at your provider',
            'rejected'            => 'Rejected',
            'undeliverable'       => 'Undeliverable',
        ],

        'errors' => [
            /**
             * 8.0.6 — two distinct failures. The first is recoverable by an
             * explicit click; the second has no retry offer at all.
             */
            'own-provider-failed'    => 'Your SMS provider rejected this message: :reason',
            'retry-via-platform'     => 'Send via AZIMIOCRM instead (:credits credits)',
            'no-platform-credits'    => 'This message needs :needed credits but you have :available. Request SMS credits to send it.',
            'sender-not-approved'    => 'Your sender name is awaiting approval and cannot send yet.',
            'opted-out'              => 'This number has opted out of your messages.',
            'invalid-number'         => 'This number could not be read as a valid phone number.',
            'quiet-hours'            => 'Marketing messages are held until :time.',
        ],
    ],
];
