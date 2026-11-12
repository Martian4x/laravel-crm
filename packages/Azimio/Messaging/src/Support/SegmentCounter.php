<?php

namespace Azimio\Messaging\Support;

use Azimio\Messaging\Enums\Encoding;

/**
 * Counts how many SMS segments a body occupies.
 *
 * Why this is not just strlen()/160:
 *
 *  - A message is GSM-7 only if EVERY character is in the GSM 03.38
 *    alphabet. One curly quote or emoji forces the whole body to UCS-2 and
 *    cuts capacity from 160 to 70, roughly tripling the cost.
 *  - Ten GSM characters (^ { } \ [ ~ ] | € and form feed) live in an
 *    extension table and cost TWO septets each, not one.
 *  - A two-unit character may not straddle a segment boundary. When one
 *    would, it moves to the next part and the previous part is left short.
 *    Ignoring this undercounts segments, which means undercharging.
 *
 * Swahili uses no characters outside GSM 03.38, so Swahili bodies stay on
 * the cheap encoding — which is the point of checking rather than assuming.
 *
 * CAVEAT: these are the GSM/3GPP rules. Beem does not document how THEY
 * count or charge on /v1/send (see 8.3), so this is our best model of the
 * cost and must be reconciled against real invoices before it is trusted
 * for billing.
 */
final class SegmentCounter
{
    /**
     * GSM 03.38 basic alphabet. Each character here costs one septet.
     *
     * The dollar, quote, apostrophe, LF and CR are appended via chr() so
     * the literal needs no escape sequences — and so PHP cannot read "$¥"
     * as a variable, which it otherwise does, since it permits high bytes
     * in identifiers.
     */
    private static function gsmBasic(): string
    {
        return '@£¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !#¤%&()*+,-./0123456789:;<=>?¡'
            .'ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà'
            .chr(36)   // $
            .chr(34)   // "
            .chr(39)   // '
            .chr(10)   // LF
            .chr(13);  // CR
    }

    /**
     * Extension table. Each of these costs two septets (escape + char).
     *
     * A method rather than a const so the backslash can be written as
     * chr(92) instead of an escape sequence that is easy to mangle.
     *
     * @return string[]
     */
    private static function gsmExtended(): array
    {
        return ['^', '{', '}', chr(92), '[', '~', ']', '|', '€', "\f"];
    }

    public function count(string $body): SegmentCount
    {
        $encoding = $this->detectEncoding($body);

        $costs = $encoding === Encoding::Gsm7
            ? $this->gsmCosts($body)
            : $this->ucs2Costs($body);

        $length = array_sum($costs);

        $single = $encoding->singleLimit();
        $multipart = $encoding->multipartLimit();

        if ($length === 0) {
            return new SegmentCount($encoding, 0, 1, $single);
        }

        if ($length <= $single) {
            return new SegmentCount($encoding, $length, 1, $single - $length);
        }

        return $this->packIntoSegments($encoding, $costs, $length, $multipart);
    }

    /**
     * GSM-7 only if every character is representable in it.
     */
    public function detectEncoding(string $body): Encoding
    {
        foreach ($this->characters($body) as $char) {
            if (! $this->isGsmCharacter($char)) {
                return Encoding::Ucs2;
            }
        }

        return Encoding::Gsm7;
    }

    /**
     * Fill parts without ever splitting a two-unit character. A character
     * that will not fit starts the next part, leaving the current one
     * short — which is exactly how a real concatenated SMS is packed.
     *
     * @param  int[]  $costs
     */
    private function packIntoSegments(
        Encoding $encoding,
        array $costs,
        int $length,
        int $multipart,
    ): SegmentCount {
        $segments = 1;
        $used = 0;

        foreach ($costs as $cost) {
            if ($used + $cost > $multipart) {
                $segments++;
                $used = 0;
            }

            $used += $cost;
        }

        return new SegmentCount($encoding, $length, $segments, $multipart - $used);
    }

    /**
     * Per-character septet cost in GSM-7.
     *
     * @return int[]
     */
    private function gsmCosts(string $body): array
    {
        return array_map(
            fn (string $char) => in_array($char, self::gsmExtended(), true) ? 2 : 1,
            $this->characters($body)
        );
    }

    /**
     * Per-character cost in UCS-2, measured in UTF-16 code units: anything
     * outside the Basic Multilingual Plane (emoji, for instance) is a
     * surrogate pair and occupies two.
     *
     * @return int[]
     */
    private function ucs2Costs(string $body): array
    {
        return array_map(
            fn (string $char) => intdiv(strlen(mb_convert_encoding($char, 'UTF-16BE', 'UTF-8')), 2),
            $this->characters($body)
        );
    }

    private function isGsmCharacter(string $char): bool
    {
        return in_array($char, self::gsmExtended(), true)
            || mb_strpos(self::gsmBasic(), $char) !== false;
    }

    /**
     * @return string[]
     */
    private function characters(string $body): array
    {
        return $body === '' ? [] : mb_str_split($body, 1, 'UTF-8');
    }
}
