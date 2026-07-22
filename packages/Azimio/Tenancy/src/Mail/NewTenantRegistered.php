<?php

namespace Azimio\Tenancy\Mail;

use Azimio\Tenancy\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewTenantRegistered extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $adminName,
        public string $adminEmail,
    ) {}

    public function build()
    {
        return $this
            ->subject('New tenant registration: '.$this->tenant->name)
            ->html(sprintf(
                '<p>A new company registered on AzimioCustomers:</p>
                <ul>
                    <li><strong>Company:</strong> %s (id %d, slug %s)</li>
                    <li><strong>Admin:</strong> %s &lt;%s&gt;</li>
                    <li><strong>Language:</strong> %s</li>
                </ul>
                <p>Manage it in the <a href="%s">tenants panel</a>.</p>',
                e($this->tenant->name),
                $this->tenant->id,
                e($this->tenant->slug),
                e($this->adminName),
                e($this->adminEmail),
                e($this->tenant->locale),
                route('super.tenants.index')
            ));
    }
}
