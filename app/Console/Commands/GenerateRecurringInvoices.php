<?php

namespace App\Console\Commands;

use App\Enums\ClientType;
use App\Enums\InvoiceStatus;
use App\Mail\RecurringInvoiceGenerated;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'invoices:generate-recurring';

    protected $description = 'Generate invoices from active recurring schedules that are due today or overdue';

    public function handle(): int
    {
        $today = now()->startOfDay();

        $schedules = RecurringInvoice::query()
            ->with(['user', 'templateInvoice.lineItems', 'templateInvoice.client'])
            ->where('is_active', true)
            ->whereDate('next_run_at', '<=', $today)
            ->get();

        $this->info("Found {$schedules->count()} schedule(s) due.");

        foreach ($schedules as $schedule) {
            try {
                $this->processSchedule($schedule);
            } catch (\Throwable $e) {
                $this->error("Failed schedule #{$schedule->id}: {$e->getMessage()}");
                report($e);
            }
        }

        return self::SUCCESS;
    }

    private function processSchedule(RecurringInvoice $schedule): void
    {
        $template = $schedule->templateInvoice;
        $user = $schedule->user;

        if (! $template || ! $user) {
            $this->warn("Schedule #{$schedule->id} has no template or user — skipping.");
            return;
        }

        $clientType = $template->client?->type ?? ClientType::External;

        DB::transaction(function () use ($schedule, $template, $user, $clientType) {
            $issueDate = now();

            $newInvoice = new Invoice([
                'issue_date' => $issueDate,
                'due_date' => $template->due_date,
                'period_from' => $template->period_from,
                'period_to' => $template->period_to,
                'status' => InvoiceStatus::AwaitingPayment,
                'currency' => $template->currency,
                'amount' => $template->amount,
                'vat_amount' => $template->vat_amount,
                'payer_memo' => $template->payer_memo,
                'payment_details' => $template->payment_details,
                'invoice_type' => $template->invoice_type,
                'vat_id' => $template->vat_id,
                'tax_id' => $template->tax_id,
                'amount_secondary' => $template->amount_secondary,
                'currency_secondary' => $template->currency_secondary,
                'is_template' => false,
            ]);

            $newInvoice->user()->associate($user);
            $newInvoice->client()->associate($template->client);
            $newInvoice->number = Invoice::nextNumberForUser($user, $clientType, $issueDate);
            $newInvoice->save();

            foreach ($template->lineItems as $index => $item) {
                $newInvoice->lineItems()->create([
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'sort_order' => $index,
                ]);
            }

            $schedule->last_run_at = now()->toDateString();
            $schedule->next_run_at = $schedule->calculateNextRunAt(now());
            $schedule->save();

            Mail::to($user->email)->queue(new RecurringInvoiceGenerated($user, $schedule, $newInvoice));

            $this->line("Generated invoice {$newInvoice->number} for schedule \"{$schedule->name}\".");
        });
    }
}
