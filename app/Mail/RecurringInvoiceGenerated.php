<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecurringInvoiceGenerated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $recipient,
        public readonly RecurringInvoice $schedule,
        public readonly Invoice $invoice,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->recipient->email,
            subject: "Invoice {$this->invoice->number} has been automatically generated",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.recurring_invoice_generated',
            with: [
                'recipientName' => $this->recipient->name,
                'scheduleName' => $this->schedule->name,
                'invoiceNumber' => $this->invoice->number,
                'invoiceAmount' => number_format((float) $this->invoice->amount, 2),
                'invoiceCurrency' => $this->invoice->currency,
                'clientName' => $this->invoice->client?->name ?? '—',
                'issueDate' => $this->invoice->issue_date?->format('M j, Y') ?? '—',
                'invoicesUrl' => route('invoices.index', ['segment' => 'external']),
                'automationUrl' => route('settings.index', ['tab' => 'automation']),
                'frequency' => ucfirst($this->schedule->frequency),
                'nextRunAt' => $this->schedule->next_run_at?->format('M j, Y') ?? '—',
            ],
        );
    }
}
