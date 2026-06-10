<?php

namespace App\Services\Reminders;

use App\Models\Invoice;
use App\Models\ReminderLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * Orchestrates smart reminders: finds open invoices that are due a reminder
 * (per ReminderPlanner), builds a channel-agnostic message, and delivers it
 * over the first configured + reachable channel for the client. Every attempt
 * is recorded in reminder_logs, which doubles as the dedupe ledger.
 */
class ReminderService
{
    public function __construct(
        private readonly ReminderPlanner $planner,
        private readonly ReminderChannelRegistry $registry,
    ) {}

    /**
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function sendForUser(User $user, ?Carbon $today = null): array
    {
        $tally = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

        if (! config('invoicly.reminders.enabled', true)) {
            return $tally;
        }

        $today = $today ?? Date::now();

        $invoices = Invoice::query()
            ->where('user_id', $user->id)
            ->openForPayment()
            ->whereNotNull('due_date')
            ->with(['client', 'user'])
            ->get();

        foreach ($invoices as $invoice) {
            $plan = $this->planner->plan($invoice, $today);
            if ($plan === null) {
                continue;
            }

            // Stage already delivered? Leave it; failed/skipped rows may retry.
            $existing = ReminderLog::query()
                ->where('invoice_id', $invoice->id)
                ->where('dedupe_key', $plan['dedupe_key'])
                ->first();

            if ($existing !== null && $existing->status === ReminderLog::STATUS_SENT) {
                continue;
            }

            $status = $this->deliver($invoice, $plan);
            $tally[$status]++;
        }

        return $tally;
    }

    /**
     * @param  array{type: 'upcoming'|'overdue', dedupe_key: string}  $plan
     * @return 'sent'|'skipped'|'failed'
     */
    private function deliver(Invoice $invoice, array $plan): string
    {
        $client = $invoice->client;
        $message = $this->buildMessage($invoice, $plan['type']);

        $channelKey = null;
        $result = null;

        foreach ($this->registry->orderedFor($client) as $key) {
            $channel = $this->registry->get($key);
            if (! $channel->isConfigured() || ! $channel->canReach($client)) {
                continue;
            }

            $channelKey = $key;
            $result = $channel->send($message);

            if ($result->ok) {
                break; // delivered — stop trying further channels
            }
        }

        $status = $result === null
            ? ReminderLog::STATUS_SKIPPED
            : ($result->ok ? ReminderLog::STATUS_SENT : ReminderLog::STATUS_FAILED);

        ReminderLog::updateOrCreate(
            ['invoice_id' => $invoice->id, 'dedupe_key' => $plan['dedupe_key']],
            [
                'user_id' => $invoice->user_id,
                'client_id' => $client->id,
                'type' => $plan['type'],
                'channel' => $channelKey,
                'status' => $status,
                'provider_message_sid' => $result?->providerMessageId,
                'error' => $result?->error,
                'sent_at' => $status === ReminderLog::STATUS_SENT ? now() : null,
            ]
        );

        if ($status === ReminderLog::STATUS_SENT) {
            $invoice->forceFill(['last_reminder_sent_at' => now()])->save();
        }

        return $status;
    }

    /**
     * @param  'upcoming'|'overdue'  $type
     */
    private function buildMessage(Invoice $invoice, string $type): ReminderMessage
    {
        $client = $invoice->client;
        $clientName = $client->name ?: ($client->business_name ?: 'there');
        $currency = $invoice->currency;
        $amount = number_format((float) $invoice->amount, 2);
        $outstanding = number_format((float) $invoice->outstandingAmount(), 2);
        $dueDate = $invoice->due_date->format('M j, Y');
        $businessName = $invoice->user->name ?: config('app.name');

        if ($type === 'overdue') {
            $subject = "Overdue: invoice {$invoice->number}";
            $body = "Hi {$clientName}, invoice {$invoice->number} for {$currency} {$amount} "
                ."was due on {$dueDate} and is now overdue. Outstanding balance: {$currency} {$outstanding}. "
                ."Please arrange payment at your earliest convenience. — {$businessName}";
        } else {
            $subject = "Reminder: invoice {$invoice->number} is due {$dueDate}";
            $body = "Hi {$clientName}, a friendly reminder that invoice {$invoice->number} for "
                ."{$currency} {$amount} is due on {$dueDate}. Outstanding balance: {$currency} {$outstanding}. "
                ."Thank you! — {$businessName}";
        }

        return new ReminderMessage(
            user: $invoice->user,
            client: $client,
            invoice: $invoice,
            type: $type,
            subject: $subject,
            body: $body,
            templateVars: [
                $clientName,
                $invoice->number,
                "{$currency} {$outstanding}",
                $dueDate,
            ],
        );
    }
}
