<?php

namespace App\Services\Reminders\Channels;

use App\Models\Client;
use App\Services\Reminders\ChannelResult;
use App\Services\Reminders\ReminderMessage;

class TwilioSmsChannel extends AbstractTwilioChannel
{
    public function key(): string
    {
        return 'sms';
    }

    public function isConfigured(): bool
    {
        return filled($this->sid())
            && filled($this->token())
            && filled(config('services.twilio.from'));
    }

    public function canReach(Client $client): bool
    {
        return filled($client->phone);
    }

    public function send(ReminderMessage $message): ChannelResult
    {
        return $this->postMessage([
            'From' => (string) config('services.twilio.from'),
            'To' => trim((string) $message->client->phone),
            'Body' => $message->body,
        ]);
    }
}
