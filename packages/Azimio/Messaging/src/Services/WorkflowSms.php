<?php

namespace Azimio\Messaging\Services;

use Azimio\Messaging\Enums\SmsMode;
use Azimio\Messaging\Models\Batch;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Models\Template;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

/**
 * The "Send SMS" workflow action (8.20).
 *
 * Kept out of the Automation package so the upstream helper only needs one
 * line, which keeps future Krayin merges cheap.
 *
 * Everything routes through SmsSender, which means a workflow send is
 * normalised, opt-out checked, quiet-hours checked, segment counted,
 * charged and logged exactly like a hand-written one. That is the point: a
 * workflow must not become the one path that can message somebody who has
 * opted out.
 */
class WorkflowSms
{
    public function __construct(
        private readonly SmsSender $sender,
        private readonly MergeFields $merge,
    ) {}

    /**
     * Send a template to the person attached to a lead.
     */
    public function sendToLeadPerson(Lead $lead, int $templateId): ?Batch
    {
        $template = Template::query()->find($templateId);

        if (! $template) {
            return null;
        }

        $person = $lead->person;

        if (! $person) {
            return null;
        }

        $number = $this->firstNumber($person);

        if (! $number) {
            return null;
        }

        $tenant = $this->tenant($lead);

        if (! $tenant) {
            return null;
        }

        $profile = $this->profile($tenant);

        if (! $profile || ! $profile->canSend()) {
            Log::info('[sms] workflow action skipped: no sending profile', [
                'lead_id' => $lead->id,
            ]);

            return null;
        }

        // Merged against the LEAD, so {{lead.title}} and {{person.*}} both
        // resolve for the record that triggered the workflow.
        $body = $this->merge->render($template->body, $lead, $tenant);

        $plan = $this->sender->plan(
            $tenant,
            $profile,
            $body,
            [['number' => $number, 'type' => Lead::class, 'id' => $lead->id]],
            $template->category,
        );

        if (! $plan->isSendable()) {
            Log::info('[sms] workflow action not sent', [
                'lead_id'  => $lead->id,
                'credits'  => $plan->creditsRequired,
                'balance'  => $plan->balance,
                'sendable' => $plan->sendableCount(),
            ]);

            return null;
        }

        return $this->sender->dispatch(
            $tenant,
            $profile,
            $plan,
            trans('messaging::app.workflow.batch-name', ['title' => $lead->title]),
        );
    }

    private function firstNumber(Person $person): ?string
    {
        foreach ((array) $person->contact_numbers as $entry) {
            $value = is_array($entry) ? ($entry['value'] ?? null) : $entry;

            if ($value) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * Workflows run in request context (Phase 3.10 note), so tenant context
     * is normally live; the lead's own tenant_id is the fallback.
     */
    private function tenant(Lead $lead): ?Tenant
    {
        $id = tenant_id() ?: $lead->tenant_id;

        return $id ? Tenant::withoutGlobalScopes()->find($id) : null;
    }

    private function profile(Tenant $tenant): ?SenderProfile
    {
        return $tenant->sms_mode === SmsMode::Own->value
            ? SenderProfile::withoutTenancy()->where('tenant_id', $tenant->id)->orderByDesc('id')->first()
            : SenderProfile::platform();
    }
}
