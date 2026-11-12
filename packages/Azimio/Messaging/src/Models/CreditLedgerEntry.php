<?php

namespace Azimio\Messaging\Models;

use Azimio\Messaging\Enums\CreditType;
use Azimio\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Webkul\User\Models\User;

/**
 * One movement in a tenant's platform credit balance. Append-only: the
 * service layer never updates or deletes these, it writes an Adjustment.
 */
class CreditLedgerEntry extends Model
{
    use BelongsToTenant;

    protected $table = 'sms_credit_ledger';

    protected $fillable = [
        'type',
        'credits',
        'balance_after',
        'reference_type',
        'reference_id',
        'created_by',
        'note',
    ];

    protected $casts = [
        'type'          => CreditType::class,
        'credits'       => 'integer',
        'balance_after' => 'integer',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference()
    {
        return $this->morphTo('reference');
    }

    /**
     * The authoritative balance for a tenant, computed from the ledger
     * rather than read from the tenants cache column. Used by the reconcile
     * action in 8.15.
     */
    public static function balanceFor(int $tenantId): int
    {
        return (int) static::withoutTenancy()->where('tenant_id', $tenantId)->sum('credits');
    }
}
