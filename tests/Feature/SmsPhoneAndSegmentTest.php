<?php

use Azimio\Messaging\Enums\Encoding;
use Azimio\Messaging\Enums\PhoneRejection;
use Azimio\Messaging\Support\PhoneNormalizer;
use Azimio\Messaging\Support\SegmentCounter;

/**
 * Phase 8.4 — phone normalization and segment counting.
 *
 * These two helpers decide what a tenant is charged and whether an opt-out
 * actually blocks anything, so they are tested harder than their size
 * suggests.
 */
function normalizer(): PhoneNormalizer
{
    return new PhoneNormalizer;
}

function counter(): SegmentCounter
{
    return new SegmentCounter;
}

it('normalizes every local spelling to the same E.164 string', function (string $input) {
    expect(normalizer()->normalize($input)->e164)->toBe('+255712345678');
})->with([
    '0712345678',
    '+255712345678',
    '255712345678',
    '712345678',
    '0255712345678',
    '255 712 345 678',
    '+255 712-345-678',
    '(0712) 345 678',
    '00255712345678',
]);

it('collapses spellings so an opt-out cannot be defeated by formatting', function () {
    // If these produced different strings, a suppressed number could be
    // messaged again simply by storing it with spaces.
    $results = normalizer()->normalizeMany(['0712345678', '+255 712 345 678', '255-712-345-678']);

    expect(collect($results)->map->e164->unique()->values()->all())->toBe(['+255712345678']);
});

it('rejects numbers it cannot make sense of, with a reason', function (string $input, PhoneRejection $reason) {
    $result = normalizer()->normalize($input);

    expect($result->isValid())->toBeFalse()
        ->and($result->rejection)->toBe($reason)
        // The raw input is kept so the user can see which row was bad.
        ->and($result->raw)->toBe($input);
})->with([
    ['', PhoneRejection::Empty],
    ['   ', PhoneRejection::Empty],
    ['abc', PhoneRejection::Unrecognised],
    ['071234', PhoneRejection::TooShort],
    ['+2557123456789012345', PhoneRejection::TooLong],
    ['0222110000', PhoneRejection::NotMobile],
]);

it('rejects a Tanzanian landline rather than charging to send to it', function () {
    // 02x is a landline and cannot receive SMS.
    expect(normalizer()->normalize('0222110000')->rejection)->toBe(PhoneRejection::NotMobile);
});

it('lets unambiguous foreign numbers through', function () {
    // A CRM with Kenyan or Ugandan contacts must not have them rejected
    // just because the default market is Tanzania.
    expect(normalizer()->normalize('+254712345678')->e164)->toBe('+254712345678')
        ->and(normalizer()->normalize('+256772123456')->e164)->toBe('+256772123456');
});

it('exposes the provider format without a plus', function () {
    expect(normalizer()->normalize('0712345678')->msisdn())->toBe('255712345678');
});

it('keeps Swahili on the cheap GSM encoding', function () {
    // Swahili uses no characters outside GSM 03.38, which is why checking
    // beats assuming.
    $result = counter()->count('Habari yako, karibu AzimioCustomers. Asante sana kwa kujiunga nasi.');

    expect($result->encoding)->toBe(Encoding::Gsm7)
        ->and($result->segments)->toBe(1);
});

it('counts GSM segment boundaries exactly', function (int $length, int $expected) {
    expect(counter()->count(str_repeat('a', $length))->segments)->toBe($expected);
})->with([
    [1, 1],
    [160, 1],
    [161, 2],
    [306, 2],
    [307, 3],
    [459, 3],
    [460, 4],
]);

it('treats an empty body as one segment, not zero', function () {
    $result = counter()->count('');

    expect($result->segments)->toBe(1)
        ->and($result->length)->toBe(0);
});

it('drops to Unicode when one character falls outside GSM', function () {
    $curly = counter()->count("Smart \u{201C}quotes\u{201D}");

    expect($curly->encoding)->toBe(Encoding::Ucs2)
        ->and($curly->isUnicode())->toBeTrue();

    // Capacity collapses from 160 to 70, which is the cost surprise the
    // compose screen has to warn about.
    expect(counter()->count(str_repeat('a', 71))->segments)->toBe(1)
        ->and(counter()->count(str_repeat("\u{201C}", 71))->segments)->toBe(2);
});

it('accepts accented characters that really are in the GSM alphabet', function () {
    // é, à, ö and ñ are GSM 03.38 characters; treating them as Unicode
    // would triple the price of a perfectly cheap message.
    expect(counter()->count('café à Öñü')->encoding)->toBe(Encoding::Gsm7);
});

it('charges two septets for GSM extension characters', function () {
    // The euro sign costs an escape plus the character.
    expect(counter()->count(str_repeat("\u{20AC}", 80))->length)->toBe(160)
        ->and(counter()->count(str_repeat("\u{20AC}", 80))->segments)->toBe(1)
        ->and(counter()->count(str_repeat("\u{20AC}", 81))->segments)->toBe(2);
});

it('never splits a two-septet character across a segment boundary', function () {
    // 153 euro signs is 306 septets. Dividing 306 by 153 says two
    // segments, but a 2-septet character cannot straddle the boundary, so
    // each part holds only 76 of them and the real answer is three.
    // Getting this wrong undercharges by a whole segment.
    $result = counter()->count(str_repeat("\u{20AC}", 153));

    expect($result->length)->toBe(306)
        ->and($result->segments)->toBe(3)
        ->and((int) ceil($result->length / 153))->toBe(2);
});

it('counts an emoji as a surrogate pair', function () {
    $result = counter()->count("Habari \u{1F600}");

    expect($result->encoding)->toBe(Encoding::Ucs2)
        // 7 characters plus a 2-unit emoji.
        ->and($result->length)->toBe(9);
});

it('multiplies credits by recipients', function () {
    $result = counter()->count(str_repeat('a', 307));

    expect($result->segments)->toBe(3)
        ->and($result->creditsPerRecipient())->toBe(3)
        ->and($result->creditsFor(10))->toBe(30)
        ->and($result->creditsFor(0))->toBe(0);
});
