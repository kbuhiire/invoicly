<?php

namespace App\Services\Reminders\Channels;

use App\Models\Client;
use App\Services\Reminders\ChannelResult;
use App\Services\Reminders\ReminderMessage;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Posts the reminder to the configured Slack channel via chat.postMessage.
 * Slack is owner-facing — it reaches the business's workspace rather than the
 * client directly, so it sits last in the default preference order as an
 * internal fallback/notification when no client-reaching channel is available.
 */
class SlackChannel implements ReminderChannel
{
    public function key(): string
    {
        return 'slack';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.slack.notifications.bot_user_oauth_token'))
            && filled(config('services.slack.notifications.channel'));
    }

    public function canReach(Client $client): bool
    {
        // The destination is the workspace channel, not a per-client address,
        // so reachability is purely a matter of configuration.
        return $this->isConfigured();
    }

    public function send(ReminderMessage $message): ChannelResult
    {
        try {
            $response = Http::withToken(config('services.slack.notifications.bot_user_oauth_token'))
                ->post('https://slack.com/api/chat.postMessage', [
                    'channel' => config('services.slack.notifications.channel'),
                    'text' => $message->body,
                ]);
        } catch (Throwable $e) {
            return ChannelResult::failed($e->getMessage());
        }

        // Slack returns 200 with {"ok": false, "error": "..."} on logical errors.
        if ($response->failed() || $response->json('ok') !== true) {
            $error = $response->json('error') ?? "HTTP {$response->status()}";

            return ChannelResult::failed("Slack: {$error}");
        }

        return ChannelResult::sent($response->json('ts'));
    }
}
