<?php

namespace Azimio\Messaging\Enums;

/**
 * Transactional vs marketing (sms_templates.category).
 *
 * This is not just a label: marketing messages are held during quiet hours
 * and get the opt-out notice appended, while transactional messages pass
 * through both (8.12).
 */
enum TemplateCategory: string
{
    case Transactional = 'transactional';
    case Marketing = 'marketing';

    public function isSubjectToQuietHours(): bool
    {
        return ! in_array(
            $this->value,
            (array) config('messaging.compliance.quiet_hours.exempt_categories', []),
            true
        );
    }

    public function needsOptOutNotice(): bool
    {
        return in_array(
            $this->value,
            (array) config('messaging.compliance.append_opt_out_notice_to', []),
            true
        );
    }
}
