<?php

namespace Azimio\Messaging\Mail;

use Azimio\Tenancy\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * "Your SMS credits are running low" (8.13).
 */
class LowSmsBalance extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly int $threshold,
    ) {
    }

    public function build()
    {
        return $this->subject(trans('messaging::app.requests.mail.low-balance-subject'))
            ->view('messaging::mail.low-balance');
    }
}
