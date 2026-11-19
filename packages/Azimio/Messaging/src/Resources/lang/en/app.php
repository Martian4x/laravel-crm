<?php

return [
    'name' => 'Messaging',

    'compose-body' => 'Message',

    /**
     * Templates (8.9).
     */
    'templates' => [
        'title'       => 'SMS Templates',
        'new'         => 'New template',
        'edit'        => 'Edit',
        'delete'      => 'Delete',
        'save'        => 'Save template',
        'name'        => 'Name',
        'locale'      => 'Language',
        'any-locale'  => 'Any language',
        'platform'    => 'Platform',
        'read-only'   => 'Read-only',
        'none'        => 'No templates yet.',
        'created'     => 'Template created.',
        'updated'     => 'Template updated.',
        'deleted'     => 'Template deleted.',
        'confirm-delete' => 'Delete this template?',
        'platform-read-only' => 'Platform templates are shared with every company and cannot be changed here.',
        'cost-note'   => 'The segment count shown in the list is what each send of this template will cost per recipient. A template is reused many times, so one extra segment is a recurring charge.',
    ],

    /**
     * Opt-outs (8.11).
     */
    'opt-outs' => [
        'title'        => 'Opt-outs',
        'help'         => 'Numbers on this list are excluded from every send automatically. The list belongs to your company only — it does not affect any other company using AzimioCustomers.',
        'add-title'    => 'Add a number',
        'add'          => 'Add',
        'import-title' => 'Import a list',
        'import'       => 'Import',
        'remove'       => 'Remove',
        'reason'       => 'Reason (optional)',
        'source'       => 'Added by',
        'source-manual'     => 'Added by hand',
        'source-import'     => 'Imported',
        'source-stop_reply' => 'Replied STOP',
        'none'         => 'No opt-outs. Every number is currently reachable.',
        'added'        => ':number will no longer receive messages.',
        'removed'      => ':number can be messaged again.',
        'imported'     => 'Imported: :added added, :skipped already on the list, :invalid could not be read.',
        'invalid-lines' => 'These lines could not be read as phone numbers and were skipped:',
        'confirm-remove' => 'Allow messages to this number again?',
    ],

    'acl' => [
        'sms'       => 'Messaging',
        'create'    => 'Send SMS',
        'templates' => 'SMS Templates',
        'opt-outs'  => 'Opt-outs',
        'bulk'     => 'Bulk SMS',
        'credits'  => 'SMS Credits',
        'logs'     => 'SMS Log',
        'settings' => 'SMS Settings',
    ],

    /**
     * Credit request loop (8.13 tenant side / 8.16 admin side).
     */
    'requests' => [
        'title'       => 'SMS Credits',
        'queue-title' => 'Credit requests',
        'new-title'   => 'Request more credits',
        'new-help'    => 'Ask the platform for more SMS credits. Payment is arranged outside the CRM; an admin records the credits once it is settled.',
        'requested'   => 'Credits requested',
        'granted'     => 'Credits granted',
        'reason'      => 'Reason',
        'submit'      => 'Send request',
        'submitted'   => 'Your request has been sent. You will be notified once it is reviewed.',
        'none'        => 'No requests yet.',
        'approve'     => 'Approve',
        'reject'      => 'Reject',
        'approved'    => 'Approved — :credits credits allocated.',
        'rejected'    => 'Request rejected.',
        'already-open' => 'You already have a request awaiting review.',
        'already-decided' => 'That request has already been decided.',
        'grant-too-small' => 'Enter at least 1 credit to approve, or use Reject.',
        'partial-help' => 'Lower the figure to approve part of the request — it is recorded as a partial approval, not a full one.',
        'ledger-note' => 'Credit request #:id',

        'status' => [
            'pending'            => 'Awaiting review',
            'approved'           => 'Approved',
            'partially-approved' => 'Partly approved',
            'rejected'           => 'Rejected',
        ],

        'mail' => [
            'submitted-subject' => ':tenant has requested SMS credits',
            'submitted-intro'   => ':tenant has asked for more SMS credits.',
            'review-link'       => 'Review the request',
            'reviewed-subject'  => 'Your SMS credit request has been reviewed',
            'reviewed-intro'    => 'Your request for SMS credits has been reviewed.',
        ],
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

        // Kept short: it is appended to every marketing message and
        // counts toward the segment cost.
        'opt-out-notice' => 'Reply STOP to opt out.',

        'compliance' => [
            'title'        => 'Sending rules',
            'help'         => 'These apply to marketing messages only. Transactional messages (quotes, reminders, confirmations) are never held.',
            'quiet-hours'  => 'Quiet hours',
            'quiet-enabled' => 'Hold marketing messages overnight',
            'quiet-from'   => 'From',
            'quiet-to'     => 'Until',
            'quiet-note'   => 'Marketing messages composed during quiet hours are queued and sent when the window opens. Leave blank to use the platform default (:start to :end).',
            'daily-cap'    => 'Daily limit',
            'daily-cap-note' => 'Maximum messages per day. Leave blank for the platform default, or 0 for no limit.',
            'cap-remaining' => ':remaining of :cap left today',
            'cap-exceeded' => 'This send is :count messages but only :remaining remain under today limit of :cap. Reduce the list or try tomorrow.',
            'save'         => 'Save sending rules',
            'saved'        => 'Sending rules saved.',
            'tcra-note'    => 'Bulk sender IDs in Tanzania are subject to TCRA and operator rules. Confirm your obligations before running large campaigns — these defaults are a starting point, not legal advice.',
        ],

        'bulk' => [
            'title'         => 'Bulk SMS',
            'mass-action'   => 'Send SMS',
            'confirm-title' => 'Confirm this send',
            'back'          => 'Back to compose',
            'name'          => 'Campaign name',
            'name-placeholder' => 'Shown in your batch history',
            'default-name'  => 'Bulk SMS',
            'numbers'       => 'Numbers',
            'numbers-placeholder' => "0712345678\n0765432109",
            'numbers-help'  => 'One per line, or separated by commas. Duplicates and opted-out numbers are removed before anything is sent.',
            'category'      => 'Type',
            'category-transactional' => 'Transactional (order, quote, reminder)',
            'category-marketing'     => 'Marketing (promotion, campaign)',
            'category-help' => 'Marketing messages are held outside permitted hours and carry an opt-out notice. Transactional messages are not.',
            'merge-note'    => 'You can use merge fields: :fields',
            'review'        => 'Review before sending',
            'from-selection' => 'Using :count record(s) selected from the list.',
            'no-audience'   => 'No recipients were given.',
            'will-send'     => 'Will send to',
            'each'          => 'per message',
            'total-credits' => 'Total credits',
            'send-now'      => 'Send to :count recipients for :credits credits',
            'queued'        => 'Queued to :count recipients.',
            'and-more'      => '…and :count more',
            'excluded-duplicates' => 'Duplicates removed',
            'excluded-optouts'    => 'Opted out, excluded',
            'excluded-invalid'    => 'Invalid, not sent',
            'cost-changed'  => 'The cost changed since you reviewed it (:confirmed, now :now credits). Check the figures and confirm again.',
        ],

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
