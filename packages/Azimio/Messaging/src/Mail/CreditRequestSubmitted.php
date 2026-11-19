<?php

namespace Azimio\Messaging\Mail;

use Azimio\Messaging\Models\CreditRequest;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells platform admins a tenant has asked for SMS credits (8.13).
 */
class CreditRequestSubmitted extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly CreditRequest $creditRequest,
    ) {}

    public function build()
    {
        return $this->subject(trans('messaging::app.requests.mail.submitted-subject', [
            'tenant' => $this->tenant->name,
        ]))->view('messaging::mail.request-submitted');
    }
}
