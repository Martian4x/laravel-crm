<?php

namespace Azimio\Messaging\Notifications;

use Azimio\Messaging\Enums\TemplateCategory;

/**
 * What a notification's toSms() returns.
 *
 * Category defaults to transactional, which is right for the things
 * notifications are: a lead assignment, an activity reminder, a quote going
 * out. Those must not be held by quiet hours (8.12) — a reminder that
 * arrives twelve hours late is not a reminder.
 */
final class SmsNotification
{
    public function __construct(
        public readonly string $body,
        public readonly TemplateCategory $category = TemplateCategory::Transactional,
    ) {
    }

    public static function make(string $body): self
    {
        return new self($body);
    }

    public function marketing(): self
    {
        return new self($this->body, TemplateCategory::Marketing);
    }
}
