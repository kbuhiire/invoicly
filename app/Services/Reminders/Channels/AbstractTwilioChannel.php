<?php

namespace App\Services\Reminders\Channels;

use App\Services\Reminders\ChannelResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Shared Twilio Messages API plumbing. SMS and WhatsApp use the same Account
 * SID / Auth Token and the same endpoint — they differ only in the address
 * format and (for WhatsApp) the use of a Content template.
 */
abstract class AbstractTwilioChannel implements ReminderChannel
{
    protected function sid(): ?string
    {
        return config('services.twilio.sid');
    }

    protected function token(): ?string
    {
        return config('services.twilio.token');
    }

    /**
     * POST to Twilio's Messages endpoint and normalise the result.
     *
     * @param  array<string, string>  $payload
     */
    protected function postMessage(array $payload): ChannelResult
    {
        $sid = $this->sid();
        $token = $this->token();

        if ($callback = config('services.twilio.status_callback')) {
            $payload['StatusCallback'] = $callback;
        }

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", $payload);
        } catch (Throwable $e) {
            return ChannelResult::failed($e->getMessage());
        }

        if ($response->failed()) {
            $message = $response->json('message') ?? "HTTP {$response->status()}";

            return ChannelResult::failed("Twilio: {$message}");
        }

        return ChannelResult::sent($response->json('sid'));
    }
}
