<?php

namespace Azimio\Tenancy;

use Azimio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Webkul\Core\Models\CoreConfig;

/**
 * The first-login checklist on the tenant dashboard (7.6).
 *
 * Each step is DERIVED from real data rather than ticked off by a flag, so
 * it cannot claim something is done when it is not — and it un-ticks itself
 * if a tenant later removes whatever satisfied it. A stored "completed"
 * flag would drift from reality the first time someone deleted their logo.
 */
class OnboardingChecklist
{
    private const DISMISS_KEY = 'azimio.onboarding.dismissed';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function steps(Tenant $tenant): array
    {
        return [
            [
                'key'   => 'logo',
                'done'  => $this->hasConfig('admin_logo.logo_image'),
                'route' => route('admin.configuration.index'),
            ],
            [
                'key'   => 'company-info',
                // TIN is the one the TRA actually asks for, so it is the
                // test for whether Company Information was filled in.
                'done'  => $this->hasConfig('general.company_information.tin'),
                'route' => route('admin.configuration.index'),
            ],
            [
                'key'   => 'team',
                // More than the one admin created at registration.
                'done'  => DB::table('users')->where('tenant_id', $tenant->id)->count() > 1,
                'route' => route('admin.settings.users.index'),
            ],
            [
                'key'   => 'first-lead',
                'done'  => DB::table('leads')->where('tenant_id', $tenant->id)->exists(),
                'route' => route('admin.leads.create', ['pipeline_id' => null]),
            ],
        ];
    }

    public function completedCount(Tenant $tenant): int
    {
        return count(array_filter($this->steps($tenant), fn ($step) => $step['done']));
    }

    public function isComplete(Tenant $tenant): bool
    {
        return $this->completedCount($tenant) === count($this->steps($tenant));
    }

    /**
     * Shown until every step is done OR the tenant dismisses it. Both
     * matter: finishing should make it vanish on its own, and someone who
     * does not want it should not have to finish it to be rid of it.
     */
    public function shouldShow(Tenant $tenant): bool
    {
        return ! $this->isComplete($tenant) && ! $this->isDismissed();
    }

    public function isDismissed(): bool
    {
        return (bool) core()->getConfigData(self::DISMISS_KEY);
    }

    /**
     * Stored in core_config, which Phase 3.8 made tenant-scoped — so one
     * tenant dismissing it does not hide it from everybody.
     */
    public function dismiss(): void
    {
        CoreConfig::query()->updateOrCreate(
            ['code' => self::DISMISS_KEY, 'channel_code' => null, 'locale_code' => null],
            ['value' => '1'],
        );
    }

    private function hasConfig(string $key): bool
    {
        return filled(core()->getConfigData($key));
    }
}
