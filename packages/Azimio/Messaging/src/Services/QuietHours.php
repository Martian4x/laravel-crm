<?php

namespace Azimio\Messaging\Services;

use Azimio\Messaging\Enums\TemplateCategory;
use Azimio\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Holds marketing messages outside acceptable hours (8.12).
 *
 * Transactional messages always pass: a quote or an appointment reminder
 * the recipient asked for is not what quiet hours exist to prevent.
 *
 * Per-tenant overrides win where set; a NULL column means "use the platform
 * default". That distinction matters — a tenant row of 0 or '' is not the
 * same as not having chosen, and treating them alike would silently turn
 * someone's sending off.
 *
 * The window normally wraps midnight (21:00 to 08:00), so "is it quiet?"
 * cannot be a between(): it is start-or-later OR before-end.
 *
 * The defaults remain a placeholder pending confirmation of the actual TCRA
 * and operator rules for bulk messaging in Tanzania.
 */
class QuietHours
{
    public function enabled(?Tenant $tenant = null): bool
    {
        $override = $tenant?->sms_quiet_hours_enabled;

        if ($override !== null) {
            return (bool) $override;
        }

        return (bool) config('messaging.compliance.quiet_hours.enabled', true);
    }

    public function timezone(): string
    {
        return (string) config('messaging.compliance.quiet_hours.timezone', 'Africa/Dar_es_Salaam');
    }

    public function start(?Tenant $tenant = null): string
    {
        return $this->setting($tenant?->sms_quiet_start, 'start', '21:00');
    }

    public function end(?Tenant $tenant = null): string
    {
        return $this->setting($tenant?->sms_quiet_end, 'end', '08:00');
    }

    /**
     * Messages this tenant may still send today, or null when uncapped.
     */
    public function dailyCap(?Tenant $tenant = null): ?int
    {
        $cap = $tenant?->sms_daily_cap ?? config('messaging.compliance.daily_send_cap');

        return $cap > 0 ? (int) $cap : null;
    }

    public function permits(TemplateCategory $category, ?CarbonInterface $at = null, ?Tenant $tenant = null): bool
    {
        if (! $this->enabled($tenant) || ! $category->isSubjectToQuietHours()) {
            return true;
        }

        return ! $this->isQuiet($at, $tenant);
    }

    /**
     * When a held message may next go out, or null if it can go now.
     */
    public function deferUntil(
        TemplateCategory $category,
        ?CarbonInterface $at = null,
        ?Tenant $tenant = null,
    ): ?CarbonImmutable {
        if ($this->permits($category, $at, $tenant)) {
            return null;
        }

        return $this->nextOpening($at, $tenant);
    }

    public function isQuiet(?CarbonInterface $at = null, ?Tenant $tenant = null): bool
    {
        $now = $this->localise($at);

        [$startH, $startM] = $this->parse($this->start($tenant));
        [$endH, $endM] = $this->parse($this->end($tenant));

        $minutes = $now->hour * 60 + $now->minute;
        $start = $startH * 60 + $startM;
        $end = $endH * 60 + $endM;

        // A window that wraps midnight is quiet at either end of the day.
        return $start > $end
            ? ($minutes >= $start || $minutes < $end)
            : ($minutes >= $start && $minutes < $end);
    }

    public function nextOpening(?CarbonInterface $at = null, ?Tenant $tenant = null): CarbonImmutable
    {
        $now = $this->localise($at);

        [$endH, $endM] = $this->parse($this->end($tenant));

        $opening = $now->setTime($endH, $endM, 0);

        // Already past today's opening while still inside the window means
        // the window runs into tomorrow.
        return $opening->lessThanOrEqualTo($now)
            ? $opening->addDay()
            : $opening;
    }

    /**
     * A NULL or empty override falls back to config; anything else is the
     * tenant's explicit choice.
     */
    private function setting(?string $override, string $key, string $fallback): string
    {
        if ($override !== null && trim($override) !== '') {
            return $override;
        }

        return (string) config('messaging.compliance.quiet_hours.'.$key, $fallback);
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
