<?php

namespace Azimio\Messaging\Models;

use Azimio\Messaging\Enums\CreditRequestStatus;
use Azimio\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Webkul\User\Models\User;

/**
 * A tenant asking the platform for SMS credits (8.13/8.16).
 */
class CreditRequest extends Model
{
    use BelongsToTenant;

    protected $table = 'sms_credit_requests';

    protected $fillable = [
        'requested_by',
        'credits_requested',
        'reason',
        'status',
        'credits_granted',
        'reviewed_by',
        'reviewed_at',
        'admin_note',
    ];

    protected $casts = [
        'status'            => CreditRequestStatus::class,
        'credits_requested' => 'integer',
        'credits_granted'   => 'integer',
        'reviewed_at'       => 'datetime',
    ];

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', CreditRequestStatus::Pending->value);
    }
}
