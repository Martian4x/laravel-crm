<?php

namespace Azimio\Messaging\Http\Controllers\Admin;

use Azimio\Messaging\Enums\SmsMode;
use Azimio\Messaging\Enums\TemplateCategory;
use Azimio\Messaging\Models\OptOut;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Models\Template;
use Azimio\Messaging\Services\CreditLedger;
use Azimio\Messaging\Services\MergeFields;
use Azimio\Messaging\Services\SmsSender;
use Azimio\Messaging\Support\PhoneNormalizer;
use Azimio\Messaging\Support\SegmentCounter;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controller;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

/**
 * Compose and send a single SMS (8.7).
 *
 * The live counter calls preview() rather than reimplementing GSM-7
 * detection in JavaScript. Decision 8.0.1 bills per segment, so the number
 * on screen has to be the number the server will charge — a second copy of
 * the segment rules in the browser is precisely the thing that would drift
 * away from it.
 */
class ComposeController extends Controller
{
    public function __construct(
        private readonly SmsSender $sender,
        private readonly SegmentCounter $segments,
        private readonly MergeFields $merge,
        private readonly PhoneNormalizer $phones,
        private readonly CreditLedger $ledger,
    ) {}

    public function index()
    {
        $tenant = $this->tenant();
        $recipient = $this->resolveRecipient();

        return view('messaging::admin.compose', [
            'tenant'          => $tenant,
            'recipient'       => $recipient,
            'recipientLabel'  => $this->labelFor($recipient),
            'recipientType'   => request('type'),
            'recipientId'     => request('id'),
            'numbers'         => $this->numbersFor($recipient),
            'prefillNumber'   => request('number'),
            'templates'       => Template::query()->orderBy('name')->get(),
            'mergeFields'     => MergeFields::available(),
            'mode'            => SmsMode::tryFrom((string) $tenant->sms_mode) ?? SmsMode::Platform,
            'platformCredits' => $this->ledger->balance($tenant),
            'hasProfile'      => $this->activeProfile($tenant) !== null,
        ]);
    }

    /**
     * Authoritative cost and preview for the body being typed.
     *
     * Everything the compose screen shows comes from here, so what the user
     * reads before sending is what the pipeline will compute.
     */
    public function preview()
    {
        $validated = request()->validate([
            'body'   => 'nullable|string|max:2000',
            'number' => 'nullable|string|max:32',
            'type'   => 'nullable|string',
            'id'     => 'nullable|integer',
        ]);

        $tenant = $this->tenant();
        $recipient = $this->resolveRecipient();

        $rendered = $this->merge->render($validated['body'] ?? '', $recipient, $tenant);

        $count = $this->segments->count($rendered);

        $number = $this->phones->normalize($validated['number'] ?? '');

        $mode = SmsMode::tryFrom((string) $tenant->sms_mode) ?? SmsMode::Platform;

        return response()->json([
            'preview'      => $rendered,
            'length'       => $count->length,
            'encoding'     => $count->encoding->value,
            'is_unicode'   => $count->isUnicode(),
            'segments'     => $count->segments,
            'credits'      => $mode->usesPlatformCredits() ? $count->creditsPerRecipient() : 0,
            'uses_credits' => $mode->usesPlatformCredits(),
            'balance'      => $this->ledger->balance($tenant),
            'number'       => $number->isValid() ? $number->e164 : null,
            'number_error' => $number->isValid() || ($validated['number'] ?? '') === ''
                ? null
                : trans($number->rejection->translationKey()),
            'opted_out'   => $number->isValid() && OptOut::blocks($number->e164),
        ]);
    }

    /**
     * Send it.
     *
     * The body is merged per recipient before planning, so the charge is
     * based on the text that actually goes out.
     */
    public function send()
    {
        $validated = request()->validate([
            'body'   => 'required|string|max:2000',
            'number' => 'required|string|max:32',
            'type'   => 'nullable|string',
            'id'     => 'nullable|integer',
        ]);

        $tenant = $this->tenant();
        $profile = $this->activeProfile($tenant);

        if (! $profile) {
            session()->flash('error', trans('messaging::app.sms.settings.no-profile'));

            return redirect()->route('admin.sms.settings.index');
        }

        $recipient = $this->resolveRecipient();

        $body = $this->merge->render($validated['body'], $recipient, $tenant);

        $plan = $this->sender->plan($tenant, $profile, $body, [[
            'number' => $validated['number'],
            'type'   => $recipient ? $recipient::class : null,
            'id'     => $recipient?->id,
        ]], TemplateCategory::Transactional);

        if (! $plan->hasAnythingToSend()) {
            $excluded = $plan->recipients[0] ?? null;

            session()->flash('error', match ($excluded?->outcome) {
                'opted_out' => trans('messaging::app.sms.errors.opted-out'),
                'invalid'   => trans($excluded->rejection->translationKey()),
                default     => trans('messaging::app.sms.compose.nothing-to-send'),
            });

            return back()->withInput();
        }

        if (! $plan->hasSufficientCredits()) {
            session()->flash('error', trans('messaging::app.sms.errors.no-platform-credits', [
                'needed'    => $plan->creditsRequired,
                'available' => $plan->balance,
            ]));

            return back()->withInput();
        }

        $batch = $this->sender->dispatch(
            $tenant,
            $profile,
            $plan,
            trans('messaging::app.sms.compose.single-batch-name'),
            auth()->id(),
        );

        session()->flash(
            $batch ? 'success' : 'error',
            $batch
                ? trans('messaging::app.sms.compose.queued', ['number' => $plan->sendable()[0]->e164])
                : trans('messaging::app.sms.compose.nothing-to-send')
        );

        return redirect()->route('admin.sms.compose.index');
    }

    /**
     * The CRM record the message is about, when composing from one.
     *
     * Only the three types that carry a phone number are accepted, and each
     * is loaded through its own tenant-scoped model, so a forged type or id
     * cannot reach another tenant's data.
     */
    private function resolveRecipient(): ?Model
    {
        $id = request('id');

        if (! $id) {
            return null;
        }

        return match (request('type')) {
            Person::class       => Person::find($id),
            Lead::class         => Lead::find($id),
            Organization::class => Organization::find($id),
            default             => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private function numbersFor(?Model $recipient): array
    {
        if (! $recipient || ! isset($recipient->contact_numbers)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($entry) => is_array($entry) ? ($entry['value'] ?? null) : $entry,
            (array) $recipient->contact_numbers
        )));
    }

    private function labelFor(?Model $recipient): ?string
    {
        return match (true) {
            $recipient instanceof Lead => $recipient->title,
            $recipient instanceof Person,
            $recipient instanceof Organization => $recipient->name,
            default                            => null,
        };
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
