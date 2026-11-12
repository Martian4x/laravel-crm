<?php

namespace Azimio\Messaging\Services;

use Azimio\Messaging\Models\Batch;
use Azimio\Messaging\Models\Message;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

/**
 * Puts a sent SMS on the person/lead timeline (8.10), so the message sits
 * with the rest of the conversation rather than only in the SMS log.
 *
 * Deliberately NOT done for large batches. A 10,000-recipient campaign
 * would write 10,000 timeline entries and bury the actual conversation on
 * every contact it touched — the batch history is the right place to see a
 * campaign. The threshold is configurable; small sends (a single message,
 * or a handful picked from a grid) are the case where a timeline entry is
 * genuinely useful.
 */
class SmsActivityLogger
{
    public function threshold(): int
    {
        return (int) config('messaging.activity.timeline_max_recipients', 10);
    }

    public function shouldLog(Batch $batch): bool
    {
        return (int) $batch->total_recipients <= $this->threshold();
    }

    /**
     * Record one message against whichever record it was addressed to.
     * Messages sent to an ad-hoc number have nothing to attach to and are
     * skipped — they remain visible in the SMS log.
     */
    public function log(Message $message): ?Activity
    {
        if (! $message->recipient_type || ! $message->recipient_id) {
            return null;
        }

        $activity = new Activity;

        $activity->forceFill([
            'title'    => trans('messaging::app.sms.log.activity-title', [
                'number' => $message->dest_addr,
            ]),
            'type'     => 'sms',
            'comment'  => $message->body,
            'is_done'  => 1,
            'user_id'  => auth()->id(),
            'schedule_from' => now(),
            'schedule_to'   => now(),
        ])->save();

        $this->attach($activity, $message);

        return $activity;
    }

    private function attach(Activity $activity, Message $message): void
    {
        $type = $message->recipient_type;
        $id = $message->recipient_id;

        if ($type === Person::class) {
            $activity->persons()->attach($id);

            return;
        }

        if ($type === Lead::class) {
            $activity->leads()->attach($id);

            // A lead's person is the one holding the phone, so the message
            // shows on their timeline too.
            $personId = Lead::withoutTenancy()->whereKey($id)->value('person_id');

            if ($personId) {
                $activity->persons()->attach($personId);
            }
        }
    }
}
