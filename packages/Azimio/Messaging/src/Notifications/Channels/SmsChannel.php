<?php

namespace Azimio\Messaging\Notifications\Channels;

use Azimio\Messaging\Enums\SmsMode;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Notifications\SmsNotification;
use Azimio\Messaging\Services\SmsSender;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Laravel notification channel for SMS (8.20, the original 5.3).
 *
 * Deliberately goes through SmsSender rather than calling a driver
 * directly. An automated send is still a send: it must be normalised,
 * checked against the opt-out list, counted for segments, charged to the
 * right account and recorded in the log exactly like a hand-written one. A
 * channel that bypassed the pipeline would be the one way to message
 * someone who had opted out.
 *
 * Usage: a notification declares `sms` in via() and returns an
 * SmsNotification from toSms(); the notifiable supplies a number from
 * routeNotificationForSms().
 */
class SmsChannel
{
    public function __construct(private readonly SmsSender $sender) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $number = $this->numberFor($notifiable);

        if (! $number) {
            return;
        }

        $tenant = $this->tenantFor($notifiable);

        if (! $tenant) {
            return;
        }

        $profile = $this->profileFor($tenant);

        if (! $profile || ! $profile->canSend()) {
            Log::info('[sms] notification skipped: no sending profile', [
                'tenant_id' => $tenant->id,
            ]);

            return;
        }

        $message = $notification->toSms($notifiable);

        if (! $message instanceof SmsNotification) {
            $message = new SmsNotification((string) $message);
        }

        $plan = $this->sender->plan(
            $tenant,
            $profile,
            $message->body,
            [$this->recipientEntry($notifiable, $number)],
            $message->category,
        );

        // A notification that cannot be paid for, is opted out, or would
        // break the daily cap is dropped with a log line rather than
        // throwing: a failed SMS must never break the thing that triggered
        // the notification.
        if (! $plan->isSendable()) {
            Log::info('[sms] notification not sent', [
                'tenant_id'  => $tenant->id,
                'credits'    => $plan->creditsRequired,
                'balance'    => $plan->balance,
                'sendable'   => $plan->sendableCount(),
                'paused'     => $plan->isPlatformPaused(),
                'over_cap'   => $plan->exceedsDailyCap(),
            ]);

            return;
        }

        $this->sender->dispatch($tenant, $profile, $plan, trans('messaging::app.sms.notification-batch'));
    }

    /**
     * @return array<string, mixed>
     */
    private function recipientEntry(mixed $notifiable, string $number): array
    {
        return [
            'number' => $number,
            'type'   => is_object($notifiable) ? $notifiable::class : null,
            'id'     => is_object($notifiable) && isset($notifiable->id) ? (int) $notifiable->id : null,
        ];
    }

    private function numberFor(mixed $notifiable): ?string
    {
        if (is_object($notifiable) && method_exists($notifiable, 'routeNotificationFor')) {
            $routed = $notifiable->routeNotificationFor('sms');

            if (is_string($routed) && $routed !== '') {
                return $routed;
            }
        }

        // Fall back to the first contact number on a CRM record.
        $numbers = is_object($notifiable) ? ($notifiable->contact_numbers ?? null) : null;

        if (is_array($numbers)) {
            foreach ($numbers as $entry) {
                $value = is_array($entry) ? ($entry['value'] ?? null) : $entry;

                if ($value) {
                    return (string) $value;
                }
            }
        }

        return null;
    }

    /**
     * Tenant context is normally already active (the notification was
     * dispatched inside a request or a tenant-stamped job, per Phase 3.10);
     * the notifiable's own tenant_id is the fallback.
     */
    private function tenantFor(mixed $notifiable): ?Tenant
    {
        if ($id = tenant_id()) {
            return Tenant::withoutGlobalScopes()->find($id);
        }

        $tenantId = is_object($notifiable) ? ($notifiable->tenant_id ?? null) : null;

        return $tenantId ? Tenant::withoutGlobalScopes()->find($tenantId) : null;
    }

    private function profileFor(Tenant $tenant): ?SenderProfile
    {
        return $tenant->sms_mode === SmsMode::Own->value
            ? SenderProfile::withoutTenancy()->where('tenant_id', $tenant->id)->orderByDesc('id')->first()
            : SenderProfile::platform();
    }
}
