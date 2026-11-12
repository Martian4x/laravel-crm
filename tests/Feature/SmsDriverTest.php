<?php

use Azimio\Messaging\DriverManager;
use Azimio\Messaging\Drivers\BeemDriver;
use Azimio\Messaging\Drivers\LogDriver;
use Azimio\Messaging\Enums\SendFailure;
use Azimio\Messaging\Enums\SmsStatus;
use Azimio\Messaging\Exceptions\DriverNotConfigured;
use Azimio\Messaging\Exceptions\DriverNotSupported;
use Azimio\Messaging\Support\OutboundMessage;
use Azimio\Messaging\Support\Recipient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Phase 8.3 — driver contract, Log driver and Beem driver.
 *
 * The Beem expectations below mirror docs.beem.africa as read on
 * 2026-11-12: body code 100 means accepted, dest_addr carries no leading
 * plus sign, encoding is the integer 0, and one request_id covers every
 * recipient in the call.
 */
function beemDriver(array $overrides = []): BeemDriver
{
    return new BeemDriver(array_merge([
        'api_key'    => 'test-key',
        'secret_key' => 'test-secret',
        'timeout'    => 5,
        'encoding'   => 0,
        'endpoints'  => [
            'send'             => 'https://apisms.beem.africa/v1/send',
            'balance'          => 'https://apisms.beem.africa/public/v1/vendors/balance',
            'delivery_reports' => 'https://dlrapi.beem.africa/public/v1/delivery-reports',
            'sender_names'     => 'https://apisms.beem.africa/public/v1/sender-names',
        ],
    ], $overrides));
}

function outbound(string $number = '+255712000001'): OutboundMessage
{
    return new OutboundMessage(
        senderName: 'AZIMIOCRM',
        body: 'Habari yako',
        recipients: [new Recipient(1, $number)],
    );
}

it('strips the plus and leading zero from destination numbers', function () {
    expect((new Recipient(1, '+255712000001'))->msisdn())->toBe('255712000001')
        ->and((new Recipient(2, '0712000001'))->msisdn())->toBe('712000001')
        ->and((new Recipient(3, '255 712 000 001'))->msisdn())->toBe('255712000001');
});

it('sends a Beem request in the documented shape', function () {
    Http::fake(['apisms.beem.africa/*' => Http::response([
        'successful' => true,
        'request_id' => 67,
        'code'       => 100,
        'message'    => 'Message Submitted Successfully',
        'valid'      => 1,
        'invalid'    => 0,
        'duplicates' => 0,
    ], 200)]);

    $result = beemDriver()->send(outbound());

    expect($result->successful)->toBeTrue()
        ->and($result->requestId)->toBe('67')
        ->and($result->accepted)->toBe(1);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://apisms.beem.africa/v1/send'
            && $request->method() === 'POST'
            && $body['source_addr'] === 'AZIMIOCRM'
            && $body['encoding'] === 0
            && $body['schedule_time'] === ''
            && $body['recipients'][0]['dest_addr'] === '255712000001'
            && $body['recipients'][0]['recipient_id'] === 1;
    });
});

it('treats a 200 with a non-100 body code as a failure', function () {
    Http::fake(['apisms.beem.africa/*' => Http::response([
        'successful' => false,
        'code'       => 111,
        'message'    => 'Invalid Sender Id',
    ], 200)]);

    $result = beemDriver()->send(outbound());

    expect($result->successful)->toBeFalse()
        ->and($result->failure)->toBe(SendFailure::InvalidSender)
        ->and($result->providerCode)->toBe('111');
});

it('maps an out-of-balance provider response for the 8.0.6 branch', function () {
    Http::fake(['apisms.beem.africa/*' => Http::response([
        'code'    => 102,
        'message' => 'Insufficient balance',
    ], 400)]);

    $result = beemDriver()->send(outbound());

    expect($result->failedBecauseOutOfBalance())->toBeTrue()
        ->and($result->isTransientFailure())->toBeFalse();
});

it('classifies an invalid number as permanent, not retryable', function () {
    Http::fake(['apisms.beem.africa/*' => Http::response(['code' => 101], 400)]);

    $result = beemDriver()->send(outbound());

    expect($result->failure)->toBe(SendFailure::InvalidNumber)
        ->and($result->isTransientFailure())->toBeFalse();
});

it('never retries a send after a transport failure', function () {
    // A dropped POST is ambiguous and Beem documents no idempotency key,
    // so retrying could charge and deliver the same message twice.
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('timed out');
    });

    $result = beemDriver()->send(outbound());

    expect($result->successful)->toBeFalse()
        ->and($result->failure)->toBe(SendFailure::ProviderError)
        ->and($result->isTransientFailure())->toBeTrue()
        // The driver must attempt the POST exactly once.
        ->and($attempts)->toBe(1);
});

it('reads the Beem balance envelope', function () {
    Http::fake(['apisms.beem.africa/*' => Http::response(['data' => ['credit_balance' => 42.5]], 200)]);

    $balance = beemDriver()->balance();

    expect($balance->amount)->toBe(42.5)
        ->and($balance->isLow(50))->toBeTrue()
        ->and($balance->isLow(10))->toBeFalse();
});

it('normalises Beem sender names and delivery statuses', function () {
    Http::fake([
        'apisms.beem.africa/*' => Http::response(['data' => [
            ['id' => '9', 'senderid' => 'AZIMIOCRM', 'status' => 'active'],
        ]], 200),
        'dlrapi.beem.africa/*' => Http::response([
            ['dest_addr' => '255712000001', 'status' => 'DELIVERED', 'request_id' => '31951'],
        ], 200),
    ]);

    $driver = beemDriver();

    expect($driver->senderNames()[0]['sender_name'])->toBe('AZIMIOCRM');

    $reports = $driver->deliveryReports('31951', ['+255712000001']);

    expect($reports[0]->status)->toBe(SmsStatus::Delivered)
        ->and($reports[0]->isPending())->toBeFalse();
});

it('treats a PENDING delivery report as not yet terminal', function () {
    Http::fake(['dlrapi.beem.africa/*' => Http::response([
        ['dest_addr' => '255712000001', 'status' => 'PENDING', 'request_id' => '1'],
    ], 200)]);

    $reports = beemDriver()->deliveryReports('1', ['+255712000001']);

    expect($reports[0]->status)->toBe(SmsStatus::Submitted)
        ->and($reports[0]->isPending())->toBeTrue();
});

it('rejects more recipients than the provider accepts', function () {
    $recipients = [];

    for ($i = 1; $i <= 1001; $i++) {
        $recipients[] = new Recipient($i, '+255712000001');
    }

    $result = beemDriver()->send(new OutboundMessage('AZIMIOCRM', 'Hi', $recipients));

    expect($result->failure)->toBe(SendFailure::TooManyRecipients);
});

it('chunks an outbound message to the provider limit', function () {
    $recipients = [];

    for ($i = 1; $i <= 250; $i++) {
        $recipients[] = new Recipient($i, '+255712000001');
    }

    $chunks = (new OutboundMessage('AZIMIOCRM', 'Hi', $recipients))->chunk(100);

    expect($chunks)->toHaveCount(3)
        ->and($chunks[0]->recipientCount())->toBe(100)
        ->and($chunks[2]->recipientCount())->toBe(50)
        ->and($chunks[0]->senderName)->toBe('AZIMIOCRM');
});

it('sends through the log driver without touching the network', function () {
    Http::fake();

    $result = app(DriverManager::class)->make('log')->send(outbound());

    expect($result->successful)->toBeTrue()
        ->and($result->requestId)->not->toBeEmpty();

    Http::assertNothingSent();
});

it('builds a fresh driver instance per resolution', function () {
    $manager = app(DriverManager::class);

    // Drivers carry one profile's credentials; sharing an instance across
    // tenants would send through the wrong account.
    expect($manager->make('log'))->not->toBe($manager->make('log'))
        ->and($manager->make('log'))->toBeInstanceOf(LogDriver::class)
        ->and($manager->make('beem'))->toBeInstanceOf(BeemDriver::class);
});

it('refuses an unknown driver', function () {
    app(DriverManager::class)->make('carrier-pigeon');
})->throws(DriverNotSupported::class);

it('refuses to send through the unimplemented Africas Talking driver', function () {
    app(DriverManager::class)->make('africastalking')->send(outbound());
})->throws(DriverNotSupported::class);

it('throws when Beem credentials are missing rather than failing silently', function () {
    (new BeemDriver(['endpoints' => ['send' => 'https://apisms.beem.africa/v1/send']]))->send(outbound());
})->throws(DriverNotConfigured::class);
