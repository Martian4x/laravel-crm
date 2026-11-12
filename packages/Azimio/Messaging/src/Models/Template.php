<?php

namespace Azimio\Messaging\Models;

use Azimio\Messaging\Enums\TemplateCategory;
use Azimio\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable message body with merge fields (8.9).
 *
 * Shares platform defaults: a NULL-tenant row is a platform template every
 * tenant can use, and BelongsToTenant's update/delete guards make it
 * read-only for them. Safe to share here because a template body holds no
 * secrets, unlike SenderProfile.
 */
class Template extends Model
{
    use BelongsToTenant;

    public static $sharesPlatformDefaults = true;

    protected $table = 'sms_templates';

    protected $fillable = [
        'name',
        'body',
        'category',
        'locale',
    ];

    protected $casts = [
        'category' => TemplateCategory::class,
    ];

    public function isPlatformDefault(): bool
    {
        return is_null($this->tenant_id);
    }
}
