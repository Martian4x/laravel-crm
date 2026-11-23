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

    /**
     * Whether this profile already holds usable provider credentials.
     *
     * This is what lets the settings form stop demanding an API key and
     * secret on every single save (9.5). The values themselves are never
     * shown back -- they are encrypted and `$hidden` -- so without a way to
     * ask "are they already there?", the only honest thing the form could
     * do was mark both fields required and make a tenant retype a secret
     * they may no longer have, just to correct a sender name.
     */
    public function hasCredentials(): bool
    {
        $credentials = $this->credentials ?? [];

        return filled($credentials['api_key'] ?? null)
            && filled($credentials['secret_key'] ?? null);
    }

    /**
     * A masked hint, safe to render: "ab••••••yz".
     *
     * Enough to recognise which key is stored without disclosing it.
     */
    public function credentialHint(): ?string
    {
        $key = $this->credentials['api_key'] ?? null;

        if (! filled($key)) {
            return null;
        }

        if (strlen($key) <= 6) {
            return str_repeat('•', strlen($key));
        }

        return substr($key, 0, 2).str_repeat('•', 6).substr($key, -2);
    }
}
