<?php

namespace Azimio\Messaging\Mail;

use Azimio\Messaging\Models\CreditRequest;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a tenant what happened to their credit request (8.16).
 */
class CreditRequestReviewed extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly CreditRequest $creditRequest,
    ) {}

    public function build()
    {
        return $this->subject(trans('messaging::app.requests.mail.reviewed-subject'))
            ->view('messaging::mail.request-reviewed');
    }
}
