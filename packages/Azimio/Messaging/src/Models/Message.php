<?php

namespace Azimio\Messaging\Models;

use Azimio\Messaging\Enums\Encoding;
use Azimio\Messaging\Enums\SmsMode;
use Azimio\Messaging\Enums\SmsStatus;
use Azimio\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * One SMS — single or part of a batch, outbound or inbound.
 */
class Message extends Model
{
    use BelongsToTenant;

    protected $table = 'sms_messages';

    protected $fillable = [
        'batch_id',
        'sender_profile_id',
        'recipient_type',
        'recipient_id',
        'dest_addr',
        'raw_number',
        'body',
        'encoding',
        'segments',
        'credits_charged',
        'cost_source',
        'direction',
        'status',
        'provider_message_id',
        'provider_request_id',
        'error_code',
        'error_message',
        'credit_shortfall',
        'scheduled_at',
        'submitted_at',
        'delivered_at',
    ];

    protected $casts = [
        'status'           => SmsStatus::class,
        'encoding'         => Encoding::class,
        'cost_source'      => SmsMode::class,
        'segments'         => 'integer',
        'credits_charged'  => 'integer',
        'credit_shortfall' => 'integer',
        'scheduled_at'     => 'datetime',
        'submitted_at'     => 'datetime',
        'delivered_at'     => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    /**
     * Drops the tenant scope for the same reason as Batch::senderProfile():
     * in platform mode this is the NULL-tenant AZIMIOCRM row, which the
     * scope would otherwise hide.
     */
    public function senderProfile()
    {
        return $this->belongsTo(SenderProfile::class, 'sender_profile_id')
            ->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Person / Lead / User this was addressed to, when it came from a CRM
     * record rather than a pasted number.
     */
    public function recipient()
    {
        return $this->morphTo('recipient');
    }

    /**
     * Decision 8.0.6: this message failed at the tenant's own provider and
     * the failure screen may offer an explicit AZIMIOCRM retry — but only
     * when the tenant actually has the credits. credit_shortfall carries
     * how many are missing, so the UI never has to recompute it.
     */
    public function canOfferPlatformRetry(): bool
    {
        return $this->status === SmsStatus::FailedOwnProvider
            && ! $this->credit_shortfall;
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }
}
