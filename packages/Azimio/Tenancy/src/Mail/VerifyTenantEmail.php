<?php

namespace Azimio\Tenancy\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Webkul\User\Models\User;

/**
 * "Confirm your email address" for a new company (7.2).
 */
class VerifyTenantEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $url,
    ) {
    }

    public function build()
    {
        return $this->subject(trans('azimio::app.verify.mail-subject'))
            ->view('azimio::mail.verify');
    }
}
