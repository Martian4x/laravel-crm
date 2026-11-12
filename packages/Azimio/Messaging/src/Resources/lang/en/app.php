<?php

return [
    'name' => 'Messaging',

    'acl' => [
        'sms'      => 'Messaging',
        'create'   => 'Send SMS',
        'logs'     => 'SMS Log',
        'settings' => 'SMS Settings',
    ],

    /**
     * Platform-admin SMS control (8.14 / 8.15).
     */
    'super' => [
        'title'            => 'Platform SMS',
        'all-messages'     => 'All messages',
        'any-tenant'       => 'Any company',
        'tenant'           => 'Company',
        'profile-title'    => 'Platform sender account',
        'profile-help'     => 'The AZIMIOCRM account every company on platform credits sends through. Its sender name must be registered with the operators.',
        'provider-balance' => 'Balance at the provider',
        'leave-blank'      => 'Leave blank to keep the stored value',
        'save-profile'     => 'Save sender account',
        'saved'            => 'Platform sender account saved.',

        'allocate-title'   => 'Allocate credits',
        'allocate-help'    => 'Record credits a company has paid for. Payment happens outside the CRM, so this is the record of it — a note is required.',
        'credits-placeholder' => 'Credits (negative to deduct)',
        'note-placeholder' => 'Reason, reference, who paid',
        'apply'            => 'Apply',
        'allocated'        => ':credits credits applied to :tenant.',
        'would-go-negative' => 'That deduction would take the balance below zero (currently :balance). A company cannot owe credits.',

        'type-allocation'  => 'Allocation',
        'type-purchase'    => 'Purchase',
        'type-adjustment'  => 'Adjustment (correction)',
        'type-expiry'      => 'Expiry',

        'ledger'           => 'Ledger',
        'ledger-balance'   => 'Ledger balance',
        'cached-balance'   => 'Cached balance',
        'balance-after'    => 'Balance after',
        'drift'            => 'Drift',
        'drift-help'       => 'The cached balance disagrees with the ledger. This is normally an abandoned reservation — a send that died between reserving and settling. Reconciling recomputes from the ledger, which returns those credits to the company.',
        'reconcile'        => 'Reconcile from ledger',
        'reconciled'       => 'Reconciled. Drift of :drift credits corrected.',
        'no-drift'         => 'No drift — the cached balance already matches the ledger.',
        'type'             => 'Type',
        'by'               => 'By',
        'note'             => 'Note',
        'no-entries'       => 'No credit movements yet.',
        'sent'             => 'Sent',
        'spent'            => 'Credits spent',
    ],

    'sms' => [
        'title' => 'SMS',

        'log' => [
            'title'          => 'SMS Log',
            'batches'        => 'Batches',
            'batch'          => 'Batch',
            'batch-name'     => 'Name',
            'status'         => 'Status',
            'number'         => 'Number',
            'when'           => 'When',
            'error'          => 'Error',
            'recipients'     => 'Recipients',
            'delivered'      => 'Delivered',
            'failed'         => 'Failed',
            'total-messages' => 'Messages',
            'any-status'     => 'Any status',
            'any-batch'      => 'Any batch',
            'filter'         => 'Filter',
            'export'         => 'Export CSV',
            'empty'          => 'No messages yet.',
            'no-batches'     => 'No batches yet.',
            'failed-summary' => ':count message(s) in this batch failed.',
            'retry-same'     => 'Retry failed',
            'retry-queued'   => ':count message(s) re-queued.',
            'nothing-to-retry' => 'There are no failed messages to retry.',
            'shortfall'      => 'Short by :credits credits to retry via AZIMIOCRM.',
            'activity-title' => 'SMS sent to :number',
        ],

        'compose' => [
            'title'             => 'Send SMS',
            'to'                => 'To',
            'message'           => 'Message',
            'template'          => 'Template',
            'no-template'       => 'No template',
            'preview'           => 'Preview',
            'characters'        => 'Characters',
            'segments'          => 'Segments',
            'credits'           => 'Credits',
            'send'              => 'Send SMS',
            'unicode-warning'   => 'Contains a non-GSM character, so capacity drops from 160 to 70 per segment.',
            'queued'            => 'Message queued to :number.',
            'nothing-to-send'   => 'There was nothing to send.',
            'single-batch-name' => 'Single message',
        ],

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
