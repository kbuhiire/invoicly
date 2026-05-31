<?php

namespace App\Services\Reminders\Channels;

use App\Models\Client;
use App\Services\Reminders\ChannelResult;
use App\Services\Reminders\ReminderMessage;

/**
 * WhatsApp via Twilio. Business-initiated reminders are outside the 24h customer
 * service window, so they must use a pre-approved Content template (ContentSid +
 * ContentVariables) rather than free-form text. Requires the client to have
 * opted in and have a WhatsApp-capable number in E.164.
 */
class TwilioWhatsAppChannel extends AbstractTwilioChannel
{
    public function key(): string
    {
        return 'whatsapp';
    }

    public function isConfigured(): bool
    {
        return filled($this->sid())
            && filled($this->token())
            && filled(config('services.twilio.whatsapp_from'));
    }

    public function canReach(Client $client): bool
    {
        return $client->whatsapp_opt_in === true && filled($client->phone);
    }

    public function send(ReminderMessage $message): ChannelResult
    {
        $contentSid = config("services.twilio.content_sids.{$message->type}");

        if (blank($contentSid)) {
            return ChannelResult::failed("No approved WhatsApp template for '{$message->type}'.");
        }

        $from = (string) config('services.twilio.whatsapp_from');

        return $this->postMessage([
            'From' => "whatsapp:{$from}",
            'To' => 'whatsapp:'.trim((string) $message->client->phone),
            'ContentSid' => $contentSid,
            'ContentVariables' => json_encode($message->templateVariables(), JSON_THROW_ON_ERROR),
        ]);
    }
}
