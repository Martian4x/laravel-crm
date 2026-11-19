<?php

use Azimio\Messaging\Enums\SenderProfileStatus;
use Azimio\Messaging\Models\Batch;
use Azimio\Messaging\Models\CreditLedgerEntry;
use Azimio\Messaging\Models\Message;
use Azimio\Messaging\Models\OptOut;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Models\Template;
use Azimio\Messaging\Notifications\Channels\SmsChannel;
use Azimio\Messaging\Notifications\SmsNotification;
use Azimio\Messaging\Services\CreditLedger;
use Azimio\Messaging\Services\WorkflowSms;
use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantManager;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Notification;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

/**
 * Phase 8.20 — the SMS notification channel and workflow action.
 *
 * The point these tests protect: an automated send goes through the SAME
 * pipeline as a hand-written one. If a notification or workflow could
 * bypass the opt-out list or the credit check, it would be the one path
 * that messages someone who asked not to be messaged.
 */
function autTenant(): Tenant
{
    return Tenant::withoutGlobalScopes()->where('slug', 'twiga-traders')->firstOrFail();
}

function autProfile(): SenderProfile
{
    $existing = SenderProfile::platform();

    if ($existing) {
        return $existing;
    }

    $profile = new SenderProfile;

    $profile->forceFill([
        'tenant_id'   => null,
        'label'       => 'Platform',
        'sender_name' => 'AZIMIOAUT',
        'driver'      => 'log',
        'status'      => SenderProfileStatus::Approved->value,
    ])->save();

    return $profile;
}

function autLead(Person $person, string $title): Lead
{
    $lead = new Lead;

    $lead->forceFill([
        'tenant_id' => autTenant()->id,
        'title'     => $title,
        'person_id' => $person->id,
        'status'    => 1,
    ])->save();

    return $lead;
}

afterEach(function () {
    foreach ([Message::class, Batch::class, OptOut::class, CreditLedgerEntry::class] as $model) {
        $model::withoutTenancy()->where('tenant_id', autTenant()->id)->forceDelete();
    }

    Template::withoutTenancy()->where('name', 'like', 'AUT %')->forceDelete();
    Lead::withoutTenancy()->where('title', 'like', 'AUT %')->forceDelete();
    Person::withoutTenancy()->where('name', 'like', 'AUT %')->forceDelete();

    SenderProfile::withoutTenancy()->where('sender_name', 'AZIMIOAUT')->forceDelete();
    Tenant::withoutGlobalScopes()->update(['sms_credits' => 0]);

    app(TenantManager::class)->forget();
});

/**
 * Krayin's Person model does not use Laravel's Notifiable trait, and making
 * it notifiable would be an upstream change this module has no business
 * forcing. The channel is therefore driven directly — it is the code under
 * test either way, and a real notification would reach it by the same call.
 */
function deliverSms(Person $person): void
{
    app(SmsChannel::class)
        ->send($person, new AutTestNotification);
}
it('registers sms as a notification channel', function () {
    expect(app(ChannelManager::class)->driver('sms'))
        ->toBeInstanceOf(SmsChannel::class);
});

it('sends a notification through the normal pipeline', function () {
    autProfile();
    app(CreditLedger::class)->allocate(autTenant(), 100);
    app(TenantManager::class)->set(autTenant());

    $person = Person::create([
        'name'            => 'AUT Amina',
        'contact_numbers' => [['value' => '0712345678', 'label' => 'work']],
    ]);

    deliverSms($person);

    $message = Message::withoutTenancy()->where('tenant_id', autTenant()->id)->first();

    expect($message)->not->toBeNull()
        ->and($message->dest_addr)->toBe('+255712345678')
        ->and($message->body)->toBe('Lead assigned to you')
        // Charged like any other send.
        ->and(app(CreditLedger::class)->balance(autTenant()))->toBe(99);
});

it('will not notify a number that has opted out', function () {
    // The whole reason the channel goes through SmsSender.
    autProfile();
    app(CreditLedger::class)->allocate(autTenant(), 100);
    app(TenantManager::class)->set(autTenant());

    OptOut::create(['dest_addr' => '+255712345678', 'source' => 'manual']);

    $person = Person::create([
        'name'            => 'AUT Baraka',
        'contact_numbers' => [['value' => '0712345678', 'label' => 'work']],
    ]);

    deliverSms($person);

    expect(Message::withoutTenancy()->where('tenant_id', autTenant()->id)->count())->toBe(0)
        ->and(app(CreditLedger::class)->balance(autTenant()))->toBe(100);
});

it('drops a notification with no credits rather than throwing', function () {
    // A failed SMS must not break whatever triggered the notification.
    autProfile();
    app(TenantManager::class)->set(autTenant());

    $person = Person::create([
        'name'            => 'AUT No Credit',
        'contact_numbers' => [['value' => '0712345678', 'label' => 'work']],
    ]);

    deliverSms($person);

    expect(Message::withoutTenancy()->where('tenant_id', autTenant()->id)->count())->toBe(0);
});

it('offers Send SMS as a lead workflow action', function () {
    app(TenantManager::class)->set(autTenant());

    Template::create(['name' => 'AUT Template', 'body' => 'Habari', 'category' => 'transactional']);

    $actions = collect(app(Webkul\Automation\Helpers\Entity\Lead::class)->getActions());

    expect($actions->pluck('id'))->toContain('send_sms_to_person');

    $action = $actions->firstWhere('id', 'send_sms_to_person');

    // Options are the tenant's own templates, so one company never sees
    // another's.
    expect(collect($action['options'])->pluck('value'))->toContain('AUT Template');
});

it('sends a templated SMS from a workflow, merged against the lead', function () {
    autProfile();
    app(CreditLedger::class)->allocate(autTenant(), 100);
    app(TenantManager::class)->set(autTenant());

    $person = Person::create([
        'name'            => 'AUT Juma Mussa',
        'contact_numbers' => [['value' => '0712345678', 'label' => 'work']],
    ]);

    $template = Template::create([
        'name'     => 'AUT Followup',
        'body'     => 'Habari {{person.first_name}}, kuhusu {{lead.title}}',
        'category' => 'transactional',
    ]);

    $lead = autLead($person, 'AUT Big deal');

    $batch = app(WorkflowSms::class)->sendToLeadPerson($lead, $template->id);

    expect($batch)->not->toBeNull();

    $message = Message::withoutTenancy()->where('tenant_id', autTenant()->id)->first();

    // Both placeholders resolved: the person through the lead's contact,
    // and the lead's own title.
    expect($message->body)->toBe('Habari AUT, kuhusu AUT Big deal')
        ->and($message->recipient_type)->toBe(Lead::class)
        ->and($message->recipient_id)->toBe($lead->id);
});

it('respects an opt-out from a workflow send too', function () {
    // A workflow must not become the one path that can message somebody
    // who opted out.
    autProfile();
    app(CreditLedger::class)->allocate(autTenant(), 100);
    app(TenantManager::class)->set(autTenant());

    $person = Person::create([
        'name'            => 'AUT Opted Out',
        'contact_numbers' => [['value' => '0712345678', 'label' => 'work']],
    ]);

    OptOut::create(['dest_addr' => '+255712345678', 'source' => 'manual']);

    $template = Template::create(['name' => 'AUT T2', 'body' => 'Habari', 'category' => 'transactional']);

    $lead = autLead($person, 'AUT Deal two');

    expect(app(WorkflowSms::class)->sendToLeadPerson($lead, $template->id))->toBeNull()
        ->and(Message::withoutTenancy()->where('tenant_id', autTenant()->id)->count())->toBe(0);
});

it('does nothing for a lead with no contact number', function () {
    autProfile();
    app(CreditLedger::class)->allocate(autTenant(), 100);
    app(TenantManager::class)->set(autTenant());

    $person = Person::create(['name' => 'AUT No Phone']);

    $template = Template::create(['name' => 'AUT T3', 'body' => 'Habari', 'category' => 'transactional']);

    $lead = autLead($person, 'AUT Deal three');

    expect(app(WorkflowSms::class)->sendToLeadPerson($lead, $template->id))->toBeNull();
});

/**
 * A minimal notification that declares the sms channel.
 */
class AutTestNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['sms'];
    }

    public function toSms($notifiable): SmsNotification
    {
        return SmsNotification::make('Lead assigned to you');
    }
}
