<?php

return [
    'name' => 'Messaging',

    'acl' => [
        'sms'      => 'Messaging',
        'settings' => 'SMS Settings',
    ],

    'sms' => [
        'title' => 'SMS',

        'settings' => [
            'title'          => 'SMS Settings',
            'platform-title' => 'Platform credits',
            'platform-help'  => 'Send using the AzimioCustomers sender name and credits allocated to your company. Nothing to set up.',
            'own-title'      => 'Your own SMS provider',
            'own-help'       => 'Send using your own provider account and your own balance. Platform credits are never used.',
            'own-label'      => 'Own provider',

            'sender-name'      => 'Sender name',
            'sender-name-help' => 'Up to 11 characters. It must already be registered and active on your provider account.',
            'provider'         => 'Provider',
            'api-key'          => 'API key',
            'secret-key'       => 'API secret',
            'credit-balance'   => 'Credit balance',
            'provider-balance' => 'Balance at your provider',

            'currently-active' => 'Currently active',
            'use-platform'     => 'Switch to platform credits',
            'save-and-verify'  => 'Save and verify',
            'verify-note'      => 'Your credentials are checked against the provider before this becomes active, so a typo cannot leave your sends failing silently.',

            'low-balance' => 'Your credit balance is running low. Request more credits before your next campaign.',

            'test-title' => 'Send a test message',
            'test-help'  => 'Sends one SMS through whichever option is active above, so you can confirm it arrives.',
            'send-test'  => 'Send test',
            'test-body'  => 'Test message from :tenant via AzimioCustomers.',

            'own-activated'      => 'Your provider was verified and is now active.',
            'platform-activated' => 'You are now sending on platform credits.',
            'test-queued'        => 'Test message queued to :number.',
            'test-failed'        => 'The test message could not be queued.',
            'no-profile'         => 'No sending option is configured yet.',

            'credentials-rejected'   => 'Your provider rejected these credentials. Check the API key and secret and try again.',
            'sender-not-on-account'  => 'The sender name ":sender" is not active on your provider account. Registered names: :names',
        ],

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
