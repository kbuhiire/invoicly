<?php

namespace App\Services\Reminders\Channels;

use App\Mail\InvoiceReminderMail;
use App\Models\Client;
use App\Services\Reminders\ChannelResult;
use App\Services\Reminders\ReminderMessage;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailChannel implements ReminderChannel
{
    public function key(): string
    {
        return 'email';
    }

    public function isConfigured(): bool
    {
        // Mail transport is always wired by the framework (smtp/log/etc).
        return true;
    }

    public function canReach(Client $client): bool
    {
        return filled($client->email);
    }

    public function send(ReminderMessage $message): ChannelResult
    {
        try {
            Mail::to($message->client->email)->send(new InvoiceReminderMail($message));

            return ChannelResult::sent();
        } catch (Throwable $e) {
            return ChannelResult::failed($e->getMessage());
        }
    }
}
