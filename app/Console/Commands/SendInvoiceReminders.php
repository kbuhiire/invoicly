<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Reminders\ReminderService;
use Illuminate\Console\Command;

class SendInvoiceReminders extends Command
{
    protected $signature = 'reminders:send
                            {--user= : Limit to a single user id}';

    protected $description = 'Send smart payment reminders for open invoices over the configured channels';

    public function handle(ReminderService $service): int
    {
        if (! config('invoicly.reminders.enabled', true)) {
            $this->warn('Reminders are disabled (invoicly.reminders.enabled=false).');

            return self::SUCCESS;
        }

        $query = User::query();
        if ($this->option('user')) {
            $query->whereKey($this->option('user'));
        }

        $totals = ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        $query->each(function (User $user) use ($service, &$totals) {
            foreach ($service->sendForUser($user) as $key => $count) {
                $totals[$key] += $count;
            }
        });

        $this->info("Reminders — sent: {$totals['sent']}, skipped: {$totals['skipped']}, failed: {$totals['failed']}.");

        return self::SUCCESS;
    }
}
