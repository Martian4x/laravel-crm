<?php

namespace Azimio\Messaging\Mail;

use Azimio\Tenancy\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * "This company's SMS are failing" — to platform admins (8.18).
 */
class SmsFailureRateAlert extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly float $rate,
        public readonly int $failed,
        public readonly int $total,
    ) {}

    public function build()
    {
        return $this->subject(trans('messaging::app.super.alert.subject', [
            'tenant' => $this->tenant->name,
        ]))->view('messaging::mail.failure-rate');
    }
}
