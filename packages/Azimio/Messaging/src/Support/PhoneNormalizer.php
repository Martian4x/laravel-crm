<?php

namespace Azimio\Messaging\Support;

use Azimio\Messaging\Enums\PhoneRejection;

/**
 * Turns however a user typed a number into E.164, or says why it cannot be.
 *
 * This is the single source of truth for number handling in the module.
 * Everything downstream — opt-out matching, dedupe, the provider's
 * dest_addr — depends on two spellings of the same number collapsing to one
 * string, or a tenant's opt-out list could be defeated simply by storing
 * "0712 345 678" instead of "+255712345678".
 *
 * Tanzania is the default market, but foreign numbers are accepted when
 * they are already unambiguous: a CRM holding Kenyan or Ugandan contacts
 * must not have them rejected.
 *
 * NOTE: the WhatsApp deep-link helper in the inline phone Blade component
 * is a browser-side copy of the same two rules (strip non-digits, turn a
 * leading 0 into the country code). It cannot call this class, since it
 * runs in the user's browser. THIS is canonical; keep that one in step.
 */
final class PhoneNormalizer
{
    /**
     * E.164 allows at most 15 digits, and no real number is under 8.
     */
    private const MIN_DIGITS = 8;

    private const MAX_DIGITS = 15;

    /**
     * Tanzanian mobile numbers are 9 national digits starting 6 or 7.
     * Landlines (2x) cannot receive SMS, so they are rejected rather than
     * silently charged for.
     */
    private const TZ_MOBILE_FIRST_DIGITS = ['6', '7'];

    public function normalize(string $raw): PhoneNumber
    {
        $trimmed = trim($raw);

        if ($trimmed === '') {
            return PhoneNumber::rejected($raw, PhoneRejection::Empty);
        }

        $countryCode = ltrim((string) config('messaging.phone.default_prefix', '+255'), '+');
        $nationalLen = (int) config('messaging.phone.national_number_len', 9);

        $digits = $this->toDigits($trimmed);

        if ($digits === '') {
            return PhoneNumber::rejected($raw, PhoneRejection::Unrecognised);
        }

        $digits = $this->applyCountryCode($digits, $trimmed, $countryCode, $nationalLen);

        if (strlen($digits) < self::MIN_DIGITS) {
            return PhoneNumber::rejected($raw, PhoneRejection::TooShort);
        }

        if (strlen($digits) > self::MAX_DIGITS) {
            return PhoneNumber::rejected($raw, PhoneRejection::TooLong);
        }

        // Local-market validation only. A foreign number that is already
        // plausible E.164 is taken at face value: we have no dial plan for
        // every country and refusing them would be worse than trying.
        if (str_starts_with($digits, $countryCode)) {
            $national = substr($digits, strlen($countryCode));

            if (strlen($national) !== $nationalLen) {
                return PhoneNumber::rejected(
                    $raw,
                    strlen($national) < $nationalLen ? PhoneRejection::TooShort : PhoneRejection::TooLong
                );
            }

            if (! in_array($national[0], self::TZ_MOBILE_FIRST_DIGITS, true)) {
                return PhoneNumber::rejected($raw, PhoneRejection::NotMobile);
            }
        }

        return PhoneNumber::valid($raw, '+'.$digits);
    }

    /**
     * Normalise many at once, keeping rejections rather than dropping them.
     *
     * @param  string[]  $numbers
     * @return PhoneNumber[]
     */
    public function normalizeMany(array $numbers): array
    {
        return array_map(fn (string $n) => $this->normalize($n), $numbers);
    }

    /**
     * Strip spaces, dashes, brackets and a leading international prefix.
     * "00" is the ITU way of writing "+", so it is treated as one.
     */
    private function toDigits(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);

        if (str_starts_with($value, '+')) {
            return $digits;
        }

        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        return $digits;
    }

    /**
     * Decide what a bare string of digits means.
     *
     * Order matters. A leading 0 is a national trunk prefix and is replaced
     * by the country code — that also covers the odd "0255..." form, where
     * stripping the 0 leaves a number that already carries the code.
     */
    private function applyCountryCode(
        string $digits,
        string $original,
        string $countryCode,
        int $nationalLen,
    ): string {
        if (str_starts_with($original, '+')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            $digits = ltrim($digits, '0');

            return str_starts_with($digits, $countryCode)
                ? $digits
                : $countryCode.$digits;
        }

        if (str_starts_with($digits, $countryCode)) {
            return $digits;
        }

        // A bare national number, e.g. 712345678.
        if (strlen($digits) === $nationalLen) {
            return $countryCode.$digits;
        }

        return $digits;
    }
}
