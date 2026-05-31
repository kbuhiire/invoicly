<?php

namespace App\Services\Reminders\Channels;

use App\Models\Client;
use App\Services\Reminders\ChannelResult;
use App\Services\Reminders\ReminderMessage;

/**
 * A delivery channel for payment reminders. Implementations are config-gated:
 * the orchestrator only attempts a channel that is both configured (credentials
 * present) and able to reach the given client.
 */
interface ReminderChannel
{
    /**
     * Stable key matching config('invoicly.reminders.channels') entries and the
     * clients.preferred_contact_method values: email|sms|whatsapp|slack.
     */
    public function key(): string;

    /**
     * Whether the credentials/config this channel needs are present.
     */
    public function isConfigured(): bool;

    /**
     * Whether this client has a usable address for this channel
     * (e.g. an email, a phone number, a WhatsApp opt-in).
     */
    public function canReach(Client $client): bool;

    public function send(ReminderMessage $message): ChannelResult;
}
