<?php

namespace Azimio\Messaging\Http\Controllers\Admin;

use Azimio\Messaging\Enums\SmsMode;
use Azimio\Messaging\Enums\TemplateCategory;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Models\Template;
use Azimio\Messaging\Services\CreditLedger;
use Azimio\Messaging\Services\MergeFields;
use Azimio\Messaging\Services\QuietHours;
use Azimio\Messaging\Services\SmsSender;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

/**
 * Bulk send (8.8).
 *
 * Three steps on purpose: compose, CONFIRM, send. The confirm step is not
 * decoration — decision 8.0.1 bills per segment, so a 2-segment body to
 * 4,000 people is 8,000 credits, and nobody should discover that after the
 * fact. Nothing is written or reserved until the final POST.
 *
 * The audience is always resolved server-side from ids or pasted text, so
 * a tampered form cannot reach another tenant's contacts: Person and Lead
 * are tenant-scoped models.
 */
class BulkController extends Controller
{
    public function __construct(
        private readonly SmsSender $sender,
        private readonly CreditLedger $ledger,
        private readonly MergeFields $merge,
    ) {}

    public function index(Request $request)
    {
        return view('messaging::admin.bulk', $this->formData($request, [
            'personIds' => [],
            'leadIds'   => [],
        ]));
    }

    /**
     * Entry point from a DataGrid mass action.
     *
     * The grid hands us its selected ids, which Phase 3.11 already scoped
     * to this tenant; they are re-resolved through the models here anyway.
     */
    public function fromSelection(Request $request)
    {
        $ids = array_filter(array_map('intval', (array) $request->input('indexes', [])));

        $type = $request->input('entity', 'person');

        return view('messaging::admin.bulk', $this->formData($request, [
            'personIds' => $type === 'person' ? $ids : [],
            'leadIds'   => $type === 'lead' ? $ids : [],
        ]));
    }

    /**
     * Work out exactly what the send would do, and show it.
     */
    public function confirm(Request $request)
    {
        $validated = $this->validateCompose($request);

        $tenant = $this->tenant();
        $profile = $this->activeProfile($tenant);

        if (! $profile) {
            session()->flash('error', trans('messaging::app.sms.settings.no-profile'));

            return redirect()->route('admin.sms.settings.index');
        }

        $recipients = $this->buildAudience($validated);

        if ($recipients === []) {
            session()->flash('error', trans('messaging::app.sms.bulk.no-audience'));

            return back()->withInput();
        }

        $plan = $this->sender->plan(
            $tenant,
            $profile,
            $validated['body'],
            $recipients,
            TemplateCategory::from($validated['category']),
        );

        return view('messaging::admin.bulk-confirm', [
            'plan'       => $plan,
            'body'       => $validated['body'],
            'category'   => $validated['category'],
            'name'       => $validated['name'] ?? null,
            'personIds'  => $validated['person_ids'] ?? [],
            'leadIds'    => $validated['lead_ids'] ?? [],
            'numbers'    => $validated['numbers'] ?? '',
            'profile'    => $profile,
            'mode'       => $plan->mode,
        ]);
    }

    /**
     * Send, but only if the cost is still what was confirmed.
     */
    public function send(Request $request)
    {
        $validated = $this->validateCompose($request);

        $confirmedCredits = (int) $request->input('confirmed_credits');

        $tenant = $this->tenant();
        $profile = $this->activeProfile($tenant);

        if (! $profile) {
            session()->flash('error', trans('messaging::app.sms.settings.no-profile'));

            return redirect()->route('admin.sms.settings.index');
        }

        $plan = $this->sender->plan(
            $tenant,
            $profile,
            $validated['body'],
            $this->buildAudience($validated),
            TemplateCategory::from($validated['category']),
        );

        // Re-planned from scratch: the audience, opt-outs or balance may
        // have moved since the confirmation screen was drawn. If the cost
        // changed, show it again rather than charging a figure nobody
        // agreed to.
        if ($plan->creditsRequired !== $confirmedCredits) {
            session()->flash('error', trans('messaging::app.sms.bulk.cost-changed', [
                'confirmed' => $confirmedCredits,
                'now'       => $plan->creditsRequired,
            ]));

            return view('messaging::admin.bulk-confirm', [
                'plan'      => $plan,
                'body'      => $validated['body'],
                'category'  => $validated['category'],
                'name'      => $validated['name'] ?? null,
                'personIds' => $validated['person_ids'] ?? [],
                'leadIds'   => $validated['lead_ids'] ?? [],
                'numbers'   => $validated['numbers'] ?? '',
                'profile'   => $profile,
                'mode'      => $plan->mode,
            ]);
        }

        if (! $plan->hasAnythingToSend()) {
            session()->flash('error', trans('messaging::app.sms.bulk.no-audience'));

            return redirect()->route('admin.sms.bulk.index');
        }

        if (! $plan->hasSufficientCredits()) {
            session()->flash('error', trans('messaging::app.sms.errors.no-platform-credits', [
                'needed'    => $plan->creditsRequired,
                'available' => $plan->balance,
            ]));

            return redirect()->route('admin.sms.credits.index');
        }

        if ($plan->exceedsDailyCap()) {
            session()->flash('error', trans('messaging::app.sms.compliance.cap-exceeded', [
                'count'     => $plan->sendableCount(),
                'remaining' => $plan->dailyCapRemaining,
                'cap'       => app(QuietHours::class)->dailyCap($tenant),
            ]));

            return redirect()->route('admin.sms.bulk.index');
        }

        $batch = $this->sender->dispatch(
            $tenant,
            $profile,
            $plan,
            // 'name' is nullable, so Laravel omits the key entirely when
            // the field is not sent — ?: alone would raise "Undefined
            // array key". Same trap as the locale bug in 260f6429.
            ($validated['name'] ?? null) ?: trans('messaging::app.sms.bulk.default-name'),
            auth()->id(),
        );

        if (! $batch) {
            session()->flash('error', trans('messaging::app.sms.compose.nothing-to-send'));

            return redirect()->route('admin.sms.bulk.index');
        }

        session()->flash('success', trans('messaging::app.sms.bulk.queued', [
            'count' => $plan->sendableCount(),
        ]));

        return redirect()->route('admin.sms.batches.show', $batch->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCompose(Request $request): array
    {
        return $request->validate([
            'body'              => 'required|string|max:2000',
            'category'          => 'required|in:transactional,marketing',
            'name'              => 'nullable|string|max:255',
            'numbers'           => 'nullable|string|max:200000',
            'person_ids'        => 'nullable|array',
            'person_ids.*'      => 'integer',
            'lead_ids'          => 'nullable|array',
            'lead_ids.*'        => 'integer',
            'confirmed_credits' => 'nullable|integer',
        ]);
    }

    /**
     * Turn the chosen audience into recipient entries for the planner.
     *
     * Contacts are resolved through the tenant-scoped models, so ids a
     * tampered form supplies simply do not resolve. Pasted numbers carry no
     * entity, which is correct — they are not CRM records.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildAudience(array $validated): array
    {
        $recipients = [];

        foreach ($this->splitNumbers($validated['numbers'] ?? '') as $number) {
            $recipients[] = ['number' => $number];
        }

        $personIds = array_filter(array_map('intval', $validated['person_ids'] ?? []));

        if ($personIds !== []) {
            foreach (Person::query()->whereIn('id', $personIds)->get() as $person) {
                foreach ($this->numbersOf($person) as $number) {
                    $recipients[] = [
                        'number' => $number,
                        'type'   => Person::class,
                        'id'     => $person->id,
                    ];
                }
            }
        }

        $leadIds = array_filter(array_map('intval', $validated['lead_ids'] ?? []));

        if ($leadIds !== []) {
            foreach (Lead::query()->with('person')->whereIn('id', $leadIds)->get() as $lead) {
                foreach ($this->numbersOf($lead->person) as $number) {
                    $recipients[] = [
                        'number' => $number,
                        'type'   => Lead::class,
                        'id'     => $lead->id,
                    ];
                }
            }
        }

        return $recipients;
    }

    /**
     * Accepts one number per line, or comma/semicolon separated — people
     * paste from spreadsheets and messaging apps, not from a spec.
     *
     * @return string[]
     */
    private function splitNumbers(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            'trim',
            preg_split('/[\r\n,;]+/', $raw) ?: []
        ), fn (string $n) => $n !== ''));
    }

    /**
     * @return string[]
     */
    private function numbersOf($record): array
    {
        if (! $record || ! isset($record->contact_numbers)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($entry) => is_array($entry) ? ($entry['value'] ?? null) : $entry,
            (array) $record->contact_numbers
        )));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, array $extra): array
    {
        $tenant = $this->tenant();

        return array_merge([
            'tenant'          => $tenant,
            'templates'       => Template::query()->orderBy('name')->get(),
            'mergeFields'     => MergeFields::available(),
            'platformCredits' => $this->ledger->balance($tenant),
            'mode'            => SmsMode::tryFrom((string) $tenant->sms_mode) ?? SmsMode::Platform,
            'hasProfile'      => $this->activeProfile($tenant) !== null,
            'maxRecipients'   => (int) config('messaging.batch.max_recipients_per_batch', 50000),
        ], $extra);
    }

    private function activeProfile(Tenant $tenant): ?SenderProfile
    {
        return $tenant->sms_mode === SmsMode::Own->value
            ? SenderProfile::query()->orderByDesc('id')->first()
            : SenderProfile::platform();
    }

    private function tenant(): Tenant
    {
        return Tenant::withoutGlobalScopes()->findOrFail(tenant_id());
    }
}
