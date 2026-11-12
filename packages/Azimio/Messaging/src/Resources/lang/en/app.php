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
        ],

        /**
         * Why a number was not sent to (8.4). Shown beside the offending
         * row so a user can fix it, never a silent drop.
         */
        'phone' => [
            'empty'        => 'No phone number was given.',
            'too-short'    => 'This number is too short to be a phone number.',
            'too-long'     => 'This number is too long to be a phone number.',
            'not-mobile'   => 'This looks like a landline, which cannot receive SMS.',
            'unrecognised' => 'This number could not be understood.',
            'quiet-hours'            => 'Marketing messages are held until :time.',
        ],
    ],
];
