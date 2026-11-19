<?php

namespace Azimio\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    public const STATUS_ACTIVE = 1;

    public const STATUS_SUSPENDED = 0;

    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'primary_color',
        'status',
        'locale',
        'currency',
        'plan',
        'max_users',
    ];

    protected $casts = [
        'status'        => 'integer',
        'max_users'     => 'integer',
        'activated_at'  => 'datetime',
        'trial_ends_at' => 'datetime',
        'suspended_at'  => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Registered but the admin has not verified their email yet (7.2).
     *
     * A third state alongside active/suspended, kept in its own column
     * rather than squeezed into `status`: a pending tenant has not been
     * refused, it is simply not finished signing up, and the two deserve
     * different messages.
     */
    public function isPending(): bool
    {
        return $this->activated_at === null;
    }

    /**
     * Usable right now: not suspended and not awaiting verification.
     */
    public function isUsable(): bool
    {
        return $this->isActive() && ! $this->isPending();
    }

    public function activate(): void
    {
        $this->forceFill(['activated_at' => now()])->save();
    }

    /**
     * One word for what is actually going on (7.9).
     *
     * "Suspended" alone tells an admin nothing: pending verification, a
     * lapsed trial and a deliberate suspension look identical in a boolean
     * but need completely different responses.
     */
    public function state(): string
    {
        if (! $this->isActive()) {
            return 'suspended';
        }

        if ($this->isPending()) {
            return 'pending';
        }

        if ($this->isTrialExpired()) {
            return 'trial-expired';
        }

        if ($this->trial_ends_at !== null) {
            return 'trial';
        }

        return 'active';
    }

    public function isTrialExpired(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isPast();
    }
}
