<?php

namespace Webkul\EmailTemplate\Models;

use Azimio\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Webkul\EmailTemplate\Contracts\EmailTemplate as EmailTemplateContract;

class EmailTemplate extends Model implements EmailTemplateContract
{
    use BelongsToTenant;

    /**
     * Rows with NULL tenant_id are platform defaults visible to every tenant.
     *
     * @var bool
     */
    public static $sharesPlatformDefaults = true;

    protected $fillable = [
        'name',
        'subject',
        'content',
    ];
}
