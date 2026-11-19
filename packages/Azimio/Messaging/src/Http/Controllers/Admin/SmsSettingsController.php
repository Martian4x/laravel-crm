<?php

namespace Azimio\Messaging\Http\Controllers\Admin;

use Azimio\Messaging\DriverManager;
use Azimio\Messaging\Enums\SenderProfileStatus;
use Azimio\Messaging\Enums\SmsMode;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Services\CreditLedger;
use Azimio\Messaging\Services\QuietHours;
use Azimio\Messaging\Services\SmsSender;
use Azimio\Messaging\Support\PhoneNormalizer;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Settings -> SMS, for a tenant admin (8.6).
 *
 * Two mutually exclusive modes (decision 8.0.4): send on the platform
 * AZIMIOCRM account using allocated credits, or on the tenant own provider
 * account using their own balance.
 */
class SmsSettingsController extends Controller
{
    public function __construct(
        private readonly DriverManager $drivers,
        private readonly CreditLedger $ledger,
        private readonly PhoneNormalizer $phones,
    ) {}

    public function index()
    {
        $tenant = $this->tenant();
        $profile = $this->ownProfile();

        return view('messaging::admin.settings', [
            'tenant'          => $tenant,
            'profile'         => $profile,
            'mode'            => SmsMode::tryFrom((string) $tenant->sms_mode) ?? SmsMode::Platform,
            'platformSender'  => config('messaging.platform.sender_name'),
            'platformCredits' => $this->ledger->balance($tenant),
            'providerBalance' => $this->providerBalance($profile),
            'drivers'         => $this->availableDrivers(),
            'quietHours'      => app(QuietHours::class),
        ]);
    }

    /**
     * Save the tenant own provider and verify it before it goes live.
     *
     * The profile is written as pending first and only activated once the
     * credentials actually work, so a typo cannot leave a tenant in a mode
     * that silently fails every send.
     */
    public function store()
    {
        $validated = request()->validate([
            'driver'      => 'required|in:'.implode(',', array_keys($this->availableDrivers())),
            'sender_name' => 'required|string|max:11',
            'api_key'     => 'required|string|max:255',
            'secret_key'  => 'required|string|max:255',
        ]);

        $tenant = $this->tenant();
        $profile = $this->ownProfile() ?? new SenderProfile;

        $profile->forceFill([
            'tenant_id'   => $tenant->id,
            'label'       => trans('messaging::app.sms.settings.own-label'),
            'sender_name' => $validated['sender_name'],
            'driver'      => $validated['driver'],
            'credentials' => [
                'api_key'    => $validated['api_key'],
                'secret_key' => $validated['secret_key'],
            ],
            'status' => SenderProfileStatus::Pending->value,
        ])->save();

        $failure = $this->verify($profile);

        if ($failure !== null) {
            session()->flash('error', $failure);

            return redirect()->route('admin.sms.settings.index');
        }

        $profile->forceFill([
            'status'      => SenderProfileStatus::Approved->value,
            'approved_at' => now(),
            'is_default'  => true,
        ])->save();

        $this->setMode($tenant, SmsMode::Own);

        session()->flash('success', trans('messaging::app.sms.settings.own-activated'));

        return redirect()->route('admin.sms.settings.index');
    }

    /**
     * Per-tenant quiet hours and daily cap (8.12).
     *
     * Blank fields are stored as NULL, which means "use the platform
     * default" — distinct from 0, which for the cap means no limit at all.
     * Collapsing the two would silently turn someone's sending off.
     */
    public function compliance(Request $request)
    {
        $validated = $request->validate([
            'quiet_hours_enabled' => 'nullable|boolean',
            'quiet_start'         => 'nullable|date_format:H:i',
            'quiet_end'           => 'nullable|date_format:H:i',
            'daily_cap'           => 'nullable|integer|min:0|max:1000000',
        ]);

        Tenant::withoutGlobalScopes()->whereKey(tenant_id())->update([
            'sms_quiet_hours_enabled' => $request->has('quiet_hours_enabled')
                ? (bool) $validated['quiet_hours_enabled']
                : null,
            'sms_quiet_start' => $validated['quiet_start'] ?? null,
            'sms_quiet_end'   => $validated['quiet_end'] ?? null,
            'sms_daily_cap'   => $validated['daily_cap'] ?? null,
        ]);

        session()->flash('success', trans('messaging::app.sms.compliance.saved'));

        return redirect()->route('admin.sms.settings.index');
    }

    /**
     * Back to platform credits in one click. The tenant own credentials are
     * kept rather than deleted: switching back should not mean retyping an
     * API secret they may no longer have to hand.
     */
    public function usePlatform()
    {
        $this->setMode($this->tenant(), SmsMode::Platform);

        session()->flash('success', trans('messaging::app.sms.settings.platform-activated'));

        return redirect()->route('admin.sms.settings.index');
    }

    /**
     * Send a test message through whichever profile is active right now.
     */
    public function test(SmsSender $sender)
    {
        $validated = request()->validate(['test_number' => 'required|string|max:32']);

        $number = $this->phones->normalize($validated['test_number']);

        if (! $number->isValid()) {
            session()->flash('error', trans($number->rejection->translationKey()));

            return redirect()->route('admin.sms.settings.index');
        }

        $tenant = $this->tenant();
        $profile = $this->activeProfile($tenant);

        if (! $profile) {
            session()->flash('error', trans('messaging::app.sms.settings.no-profile'));

            return redirect()->route('admin.sms.settings.index');
        }

        $body = trans('messaging::app.sms.settings.test-body', ['tenant' => $tenant->name]);

        $plan = $sender->plan($tenant, $profile, $body, [$number->e164]);

        if (! $plan->hasSufficientCredits()) {
            session()->flash('error', trans('messaging::app.sms.errors.no-platform-credits', [
                'needed'    => $plan->creditsRequired,
                'available' => $plan->balance,
            ]));

            return redirect()->route('admin.sms.settings.index');
        }

        $batch = $sender->dispatch($tenant, $profile, $plan, 'Test message', auth()->id());

        session()->flash(
            $batch ? 'success' : 'error',
            $batch
                ? trans('messaging::app.sms.settings.test-queued', ['number' => $number->e164])
                : trans('messaging::app.sms.settings.test-failed')
        );

        return redirect()->route('admin.sms.settings.index');
    }

    /**
     * Check the credentials really work before trusting them.
     *
     * Where the driver can list sender names, the submitted one is checked
     * against the account: a sender ID not registered with the operator is
     * rejected on every send, and finding that out here beats finding out
     * during a 5,000-recipient campaign.
     *
     * Returns an error message, or null when everything checks out.
     */
    private function verify(SenderProfile $profile): ?string
    {
        $driver = $this->drivers->for($profile);

        $senderNames = $driver->senderNames();

        if ($senderNames === [] && $driver->balance() === null) {
            return trans('messaging::app.sms.settings.credentials-rejected');
        }

        if ($senderNames !== []) {
            $names = array_values(array_filter(array_map(
                fn ($row) => $row['sender_name'] ?? null,
                $senderNames
            )));

            if ($names !== [] && ! in_array($profile->sender_name, $names, true)) {
                return trans('messaging::app.sms.settings.sender-not-on-account', [
                    'sender' => $profile->sender_name,
                    'names'  => implode(', ', $names),
                ]);
            }
        }

        return null;
    }

    private function providerBalance(?SenderProfile $profile): ?float
    {
        if (! $profile || ! $profile->canSend()) {
            return null;
        }

        return $this->drivers->for($profile)->balance()?->amount;
    }

    private function ownProfile(): ?SenderProfile
    {
        return SenderProfile::query()->orderByDesc('id')->first();
    }

    /**
     * Whichever profile a send would actually go through right now.
     */
    private function activeProfile(Tenant $tenant): ?SenderProfile
    {
        return $tenant->sms_mode === SmsMode::Own->value
            ? $this->ownProfile()
            : SenderProfile::platform();
    }

    private function setMode(Tenant $tenant, SmsMode $mode): void
    {
        Tenant::withoutGlobalScopes()
            ->whereKey($tenant->id)
            ->update(['sms_mode' => $mode->value]);
    }

    /**
     * Drivers a tenant may choose. The log driver is development-only and
     * is never offered in production.
     *
     * @return array<string, string>
     */
    private function availableDrivers(): array
    {
        $drivers = ['beem' => 'Beem Africa'];

        if (! app()->environment('production')) {
            $drivers['log'] = 'Log (development only)';
        }

        return $drivers;
    }

    private function tenant(): Tenant
    {
        return Tenant::withoutGlobalScopes()->findOrFail(tenant_id());
    }
}
