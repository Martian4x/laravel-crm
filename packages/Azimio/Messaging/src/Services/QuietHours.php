<?php

namespace Azimio\Messaging\Services;

use Azimio\Messaging\Enums\TemplateCategory;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Holds marketing messages outside acceptable hours (8.12).
 *
 * Transactional messages always pass: a quote or an appointment reminder
 * the recipient asked for is not what quiet hours exist to prevent.
 *
 * The window normally wraps midnight (21:00 to 08:00), so "is it quiet?"
 * cannot be a simple between() — it is start-or-later OR before-end.
 *
 * Per-tenant overrides land in 8.12; this currently reads platform config,
 * which is the fallback those overrides will fall back TO.
 *
 * The defaults are a placeholder pending confirmation of the actual TCRA
 * and operator rules for bulk messaging in Tanzania.
 */
class QuietHours
{
    public function enabled(): bool
    {
        return (bool) config('messaging.compliance.quiet_hours.enabled', true);
    }

    public function timezone(): string
    {
        return (string) config('messaging.compliance.quiet_hours.timezone', 'Africa/Dar_es_Salaam');
    }

    /**
     * Whether this category may send right now.
     */
    public function permits(TemplateCategory $category, ?CarbonInterface $at = null): bool
    {
        if (! $this->enabled() || ! $category->isSubjectToQuietHours()) {
            return true;
        }

        return ! $this->isQuiet($at);
    }

    /**
     * When a held message may next go out, or null if it can go now.
     */
    public function deferUntil(TemplateCategory $category, ?CarbonInterface $at = null): ?CarbonImmutable
    {
        if ($this->permits($category, $at)) {
            return null;
        }

        return $this->nextOpening($at);
    }

    public function isQuiet(?CarbonInterface $at = null): bool
    {
        $now = $this->localise($at);

        [$startH, $startM] = $this->parse((string) config('messaging.compliance.quiet_hours.start', '21:00'));
        [$endH, $endM] = $this->parse((string) config('messaging.compliance.quiet_hours.end', '08:00'));

        $minutes = $now->hour * 60 + $now->minute;
        $start = $startH * 60 + $startM;
        $end = $endH * 60 + $endM;

        // A window that wraps midnight is quiet at either end of the day.
        return $start > $end
            ? ($minutes >= $start || $minutes < $end)
            : ($minutes >= $start && $minutes < $end);
    }

    /**
     * The next moment the window opens.
     */
    public function nextOpening(?CarbonInterface $at = null): CarbonImmutable
    {
        $now = $this->localise($at);

        [$endH, $endM] = $this->parse((string) config('messaging.compliance.quiet_hours.end', '08:00'));

        $opening = $now->setTime($endH, $endM, 0);

        // Already past today's opening while still inside the window means
        // the window runs into tomorrow.
        return $opening->lessThanOrEqualTo($now)
            ? $opening->addDay()
            : $opening;
    }

    private function localise(?CarbonInterface $at): CarbonImmutable
    {
        return CarbonImmutable::parse($at ?? CarbonImmutable::now())->setTimezone($this->timezone());
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function parse(string $time): array
    {
        $parts = explode(':', $time);

        return [(int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0)];
    }
}
