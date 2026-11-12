<?php

namespace Azimio\Messaging\Models;

use Azimio\Messaging\Enums\SenderProfileStatus;
use Azimio\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Webkul\User\Models\User;

/**
 * A sender identity: who a tenant sends as, and through which provider.
 *
 * NOT $sharesPlatformDefaults, deliberately. The NULL-tenant row is the
 * platform AZIMIOCRM profile and it holds the platform's provider
 * credentials; sharing it would let any tenant read them back decrypted
 * through the cast below. The sending service loads the platform profile
 * explicitly via platform(), and tenant UI shows the sender name from
 * config instead of from this row.
 */
class SenderProfile extends Model
{
    use BelongsToTenant;

    protected $table = 'sms_sender_profiles';

    protected $fillable = [
        'label',
        'sender_name',
        'driver',
        'credentials',
        'is_default',
        'status',
        'approved_at',
        'approved_by',
        'notes',
    ];

    protected $casts = [
        // Encrypted at rest (decision 8.0.5) — APP_KEY is backup-critical.
        'credentials' => 'encrypted:json',
        'is_default'  => 'boolean',
        'status'      => SenderProfileStatus::class,
        'approved_at' => 'datetime',
    ];

    /**
     * Keep credentials out of anything that serialises a model: logs,
     * API responses, queued job payloads, debug output.
     */
    protected $hidden = [
        'credentials',
    ];

    /**
     * The platform's own AZIMIOCRM profile. Bypasses tenancy on purpose —
     * see the class docblock.
     */
    public static function platform(): ?self
    {
        return static::withoutTenancy()->whereNull('tenant_id')->first();
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPlatform(): bool
    {
        return is_null($this->tenant_id);
    }

    public function canSend(): bool
    {
        return $this->status->canSend();
    }
}
