<?php

namespace Azimio\Messaging\Models;

use Azimio\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Webkul\Contact\Models\Person;

/**
 * A number that must not be messaged by this tenant (8.11).
 *
 * Scoped per tenant on purpose: opting out of one company's messages does
 * not silence another company the person actually deals with.
 */
class OptOut extends Model
{
    use BelongsToTenant;

    protected $table = 'sms_opt_outs';

    protected $fillable = [
        'dest_addr',
        'source',
        'person_id',
        'reason',
    ];

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    /**
     * Whether this tenant is barred from messaging a number. dest_addr is
     * stored normalised, so callers must pass the E.164 form.
     */
    public static function blocks(string $destAddr): bool
    {
        return static::where('dest_addr', $destAddr)->exists();
    }
}
