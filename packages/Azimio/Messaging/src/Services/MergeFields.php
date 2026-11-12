<?php

namespace Azimio\Messaging\Services;

use Azimio\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

/**
 * Fills {{placeholders}} in a message body from a CRM record (8.7/8.9).
 *
 * Two rules worth stating, because both cost money if broken:
 *
 *  - An unknown or unresolvable placeholder renders as EMPTY, never as the
 *    literal {{person.first_name}}. Sending raw template syntax to a
 *    customer is worse than sending a slightly awkward sentence, and it is
 *    charged for either way.
 *  - Rendering happens per recipient BEFORE segment counting, because a
 *    merged name changes the length and therefore the price. Counting the
 *    template rather than the rendered text would understate the cost of
 *    every long name.
 */
class MergeFields
{
    /**
     * Placeholders offered in the UI, with a short description key.
     *
     * @return array<string, string>
     */
    public static function available(): array
    {
        return [
            'person.name'       => 'person-name',
            'person.first_name' => 'person-first-name',
            'person.job_title'  => 'person-job-title',
            'organization.name' => 'organization-name',
            'lead.title'        => 'lead-title',
            'user.name'         => 'user-name',
            'tenant.name'       => 'tenant-name',
        ];
    }

    /**
     * Render a body against one recipient record.
     */
    public function render(string $body, ?Model $recipient = null, ?Tenant $tenant = null): string
    {
        $values = $this->valuesFor($recipient, $tenant);

        return preg_replace_callback(
            '/\{\{\s*([a-z_]+\.[a-z_]+)\s*\}\}/i',
            fn (array $m) => (string) ($values[strtolower($m[1])] ?? ''),
            $body
        );
    }

    /**
     * Whether a body contains any placeholder at all — used to decide
     * whether a preview needs a recipient to be meaningful.
     */
    public function hasPlaceholders(string $body): bool
    {
        return (bool) preg_match('/\{\{\s*[a-z_]+\.[a-z_]+\s*\}\}/i', $body);
    }

    /**
     * @return array<string, string>
     */
    private function valuesFor(?Model $recipient, ?Tenant $tenant): array
    {
        $values = [
            'user.name'   => (string) (auth()->user()?->name ?? ''),
            'tenant.name' => (string) ($tenant?->name ?? ''),
        ];

        $person = $this->resolvePerson($recipient);

        if ($person) {
            $values['person.name'] = (string) $person->name;
            $values['person.first_name'] = $this->firstName((string) $person->name);
            $values['person.job_title'] = (string) ($person->job_title ?? '');
            $values['organization.name'] = (string) ($person->organization?->name ?? '');
        }

        if ($recipient instanceof Lead) {
            $values['lead.title'] = (string) $recipient->title;
        }

        if ($recipient instanceof Organization) {
            $values['organization.name'] = (string) $recipient->name;
        }

        return $values;
    }

    /**
     * A lead's person is the one actually holding the phone, so a lead
     * resolves person.* through its contact rather than leaving them blank.
     */
    private function resolvePerson(?Model $recipient): ?Person
    {
        if ($recipient instanceof Person) {
            return $recipient;
        }

        if ($recipient instanceof Lead) {
            return $recipient->person;
        }

        return null;
    }

    private function firstName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        return $parts[0] ?? '';
    }
}
