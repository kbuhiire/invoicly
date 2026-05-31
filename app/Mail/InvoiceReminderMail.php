<?php

namespace App\Mail;

use App\Services\Reminders\ReminderMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public readonly string $reminderType;

    public readonly string $subjectLine;

    public readonly string $clientName;

    public readonly string $invoiceNumber;

    public readonly string $amount;

    public readonly string $outstanding;

    public readonly string $currency;

    public readonly string $dueDate;

    public readonly string $businessName;

    public function __construct(ReminderMessage $message)
    {
        $invoice = $message->invoice;

        $this->reminderType = $message->type;
        $this->subjectLine = $message->subject;
        $this->clientName = $message->client->name ?: ($message->client->business_name ?: 'there');
        $this->invoiceNumber = (string) $invoice->number;
        $this->amount = number_format((float) $invoice->amount, 2);
        $this->outstanding = number_format((float) $invoice->outstandingAmount(), 2);
        $this->currency = (string) $invoice->currency;
        $this->dueDate = $invoice->due_date->format('M j, Y');
        $this->businessName = $message->user->name ?: (string) config('app.name');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.invoice_reminder',
            with: [
                'isOverdue' => $this->reminderType === 'overdue',
                'clientName' => $this->clientName,
                'invoiceNumber' => $this->invoiceNumber,
                'amount' => $this->amount,
                'outstanding' => $this->outstanding,
                'currency' => $this->currency,
                'dueDate' => $this->dueDate,
                'businessName' => $this->businessName,
            ],
        );
    }
}
