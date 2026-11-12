<?php

namespace Azimio\Messaging\Models;

use Azimio\Messaging\Enums\BatchStatus;
use Azimio\Tenancy\Concerns\BelongsToTenant;
use Azimio\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Webkul\User\Models\User;

/**
 * One bulk send (8.8). Recipients are the related Message rows.
 */
class Batch extends Model
{
    use BelongsToTenant;

    protected $table = 'sms_batches';

    protected $fillable = [
        'sender_profile_id',
        'template_id',
        'name',
        'body',
        'audience_type',
        'total_recipients',
        'queued_count',
        'submitted_count',
        'delivered_count',
        'failed_count',
        'credits_estimated',
        'credits_charged',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'status'            => BatchStatus::class,
        'total_recipients'  => 'integer',
        'queued_count'      => 'integer',
        'submitted_count'   => 'integer',
        'delivered_count'   => 'integer',
        'failed_count'      => 'integer',
        'credits_estimated' => 'integer',
        'credits_charged'   => 'integer',
        'scheduled_at'      => 'datetime',
        'started_at'        => 'datetime',
        'completed_at'      => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(Message::class, 'batch_id');
    }

    /**
     * The profile this batch sent through.
     *
     * Drops the tenant scope deliberately. In platform mode the profile is
     * the NULL-tenant AZIMIOCRM row, which the scope would hide — leaving
     * this relation null and every platform send failing as "sender not
     * approved". Reaching it this way is safe because the batch itself is
     * tenant-scoped, so a tenant can only ever arrive here through their
     * own batch, and SenderProfile keeps `credentials` in $hidden.
     */
    public function senderProfile()
    {
        return $this->belongsTo(SenderProfile::class, 'sender_profile_id')
            ->withoutGlobalScope(TenantScope::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Recount the denormalised per-status counters from the message rows.
     * The counters exist so batch pages do not aggregate tens of thousands
     * of rows on every load, but they are derived — this is the truth.
     */
    public function recountFromMessages(): array
    {
        return $this->messages()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }
}
