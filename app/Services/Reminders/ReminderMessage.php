<?php

namespace App\Services\Reminders;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;

/**
 * Channel-agnostic reminder payload. Free-text channels (email/SMS/Slack) use
 * $subject/$body; WhatsApp business-initiated messages must use a pre-approved
 * Content template instead, so the ordered $templateVars carry the same facts
 * for the template's {{1}}, {{2}}, … placeholders.
 */
final class ReminderMessage
{
    /**
     * @param  'upcoming'|'overdue'  $type
     * @param  list<string>  $templateVars  positional WhatsApp template variables
     */
    public function __construct(
        public readonly User $user,
        public readonly Client $client,
        public readonly Invoice $invoice,
        public readonly string $type,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $templateVars,
    ) {}

    /**
     * WhatsApp Content API expects variables keyed by position ("1","2",…).
     *
     * @return array<string, string>
     */
    public function templateVariables(): array
    {
        $keyed = [];
        foreach (array_values($this->templateVars) as $i => $value) {
            $keyed[(string) ($i + 1)] = $value;
        }

        return $keyed;
    }
}
