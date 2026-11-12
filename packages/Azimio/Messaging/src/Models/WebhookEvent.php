<?php

namespace Azimio\Messaging\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A raw provider callback, stored before it is trusted (8.19).
 *
 * Deliberately NOT tenant-scoped and NOT using BelongsToTenant: a callback
 * arrives with no tenant context and is attributed to one only once
 * message_id resolves. Platform admins read this; tenants never do.
 *
 * Rows with signature_valid = false are kept rather than discarded — a run
 * of them is what a forged-callback attempt looks like.
 */
class WebhookEvent extends Model
{
    protected $table = 'sms_webhook_events';

    protected $fillable = [
        'provider',
        'event_type',
        'payload',
        'signature_valid',
        'message_id',
        'processed_at',
    ];

    protected $casts = [
        'payload'         => 'array',
        'signature_valid' => 'boolean',
        'processed_at'    => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function scopeUnprocessed($query)
    {
        return $query->whereNull('processed_at');
    }
}
