<?php

namespace Azimio\Messaging\Services;

use Azimio\Messaging\Enums\SmsMode;
use Azimio\Messaging\Enums\TemplateCategory;
use Azimio\Messaging\Models\OptOut;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Support\PhoneNormalizer;
use Azimio\Messaging\Support\PlannedRecipient;
use Azimio\Messaging\Support\SegmentCounter;
use Azimio\Messaging\Support\SendPlan;
use Azimio\Tenancy\Models\Tenant;

/**
 * Works out exactly what a send will do before it does any of it.
 *
 * Runs the gates in a deliberate order — normalize, then dedupe, then
 * opt-out — because each depends on the one before:
 *
 *  - Dedupe must happen on the NORMALISED number, or "0712 345 678" and
 *    "+255712345678" count as two recipients and bill twice for one phone.
 *  - Opt-out must be checked on the normalised number too, or a suppression
 *    is defeated by a different spelling.
 *
 * Nothing here writes or charges. The same plan the user confirms is the
 * one the pipeline executes, so the quoted price cannot drift from the
 * charged price.
 */
class SendPlanner
{
    public function __construct(
        private readonly PhoneNormalizer $phones,
        private readonly SegmentCounter $segments,
        private readonly QuietHours $quietHours,
        private readonly CreditLedger $ledger,
    ) {
    }

    /**
     * @param  array<int, string|array{number: string, type?: string, id?: int}>  $recipients
     */
    public function plan(
        Tenant $tenant,
        SenderProfile $profile,
        string $body,
        array $recipients,
        TemplateCategory $category = TemplateCategory::Transactional,
    ): SendPlan {
        $segments = $this->segments->count($body);

        $planned = $this->classify($recipients);

        $mode = $profile->isPlatform() ? SmsMode::Platform : SmsMode::Own;

        $sendable = count(array_filter($planned, fn ($r) => $r->isSendable()));

        return new SendPlan(
            recipients: $planned,
            segments: $segments,
            mode: $mode,
            // Cost is segments x recipients (decision 8.0.1), and only
            // platform mode spends platform credits.
            creditsRequired: $mode->usesPlatformCredits() ? $segments->creditsFor($sendable) : 0,
            balance: $this->ledger->balance($tenant),
            deferredUntil: $this->quietHours->deferUntil($category),
        );
    }

    /**
     * @param  array<int, string|array<string, mixed>>  $recipients
     * @return PlannedRecipient[]
     */
    private function classify(array $recipients): array
    {
        $seen = [];
        $planned = [];

        // One query for the whole opt-out list beats one per recipient; a
        // 10,000-row batch would otherwise be 10,000 round trips.
        $optedOut = OptOut::query()->pluck('dest_addr')->flip();

        foreach ($recipients as $entry) {
            [$raw, $type, $id] = $this->unpack($entry);

            $number = $this->phones->normalize($raw);

            if (! $number->isValid()) {
                $planned[] = PlannedRecipient::invalid($raw, $number->rejection);

                continue;
            }

            $e164 = $number->e164;

            if (isset($seen[$e164])) {
                $planned[] = PlannedRecipient::duplicate($raw, $e164);

                continue;
            }

            $seen[$e164] = true;

            if ($optedOut->has($e164)) {
                $planned[] = PlannedRecipient::optedOut($raw, $e164);

                continue;
            }

            $planned[] = PlannedRecipient::sendable($raw, $e164, $type, $id);
        }

        return $planned;
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?int}
     */
    private function unpack(string|array $entry): array
    {
        if (is_string($entry)) {
            return [$entry, null, null];
        }

        return [
            (string) ($entry['number'] ?? ''),
            $entry['type'] ?? null,
            isset($entry['id']) ? (int) $entry['id'] : null,
        ];
    }
}
