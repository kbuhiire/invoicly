<?php

namespace App\Services\Reminders;

use App\Models\Client;
use App\Services\Reminders\Channels\EmailChannel;
use App\Services\Reminders\Channels\ReminderChannel;
use App\Services\Reminders\Channels\SlackChannel;
use App\Services\Reminders\Channels\TwilioSmsChannel;
use App\Services\Reminders\Channels\TwilioWhatsAppChannel;

/**
 * Holds the available reminder channels keyed by their key() and resolves the
 * order in which they should be tried for a given client: the client's own
 * preferred_contact_method first (when set), then the configured global order.
 */
class ReminderChannelRegistry
{
    /** @var array<string, ReminderChannel> */
    private array $channels;

    public function __construct(
        EmailChannel $email,
        TwilioSmsChannel $sms,
        TwilioWhatsAppChannel $whatsapp,
        SlackChannel $slack,
    ) {
        foreach ([$email, $sms, $whatsapp, $slack] as $channel) {
            $this->channels[$channel->key()] = $channel;
        }
    }

    public function get(string $key): ?ReminderChannel
    {
        return $this->channels[$key] ?? null;
    }

    /**
     * Ordered, de-duplicated channel keys to attempt for this client.
     *
     * @return list<string>
     */
    public function orderedFor(Client $client): array
    {
        /** @var list<string> $configured */
        $configured = (array) config('invoicly.reminders.channels', []);

        $preferred = $client->preferred_contact_method;
        $order = ($preferred && isset($this->channels[$preferred]))
            ? array_merge([$preferred], $configured)
            : $configured;

        // De-dupe while preserving order, dropping unknown keys.
        $seen = [];
        $result = [];
        foreach ($order as $key) {
            if (isset($this->channels[$key]) && ! isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $key;
            }
        }

        return $result;
    }
}
