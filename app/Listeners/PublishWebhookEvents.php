<?php

namespace App\Listeners;

use App\Events\InvoiceReconciled;
use App\Events\PaymentReceived;
use App\Services\Webhooks\EventPublisher;
use App\Services\Webhooks\WebhookPayload;

/**
 * Bridges internal domain events to the outbound webhook surface: whenever a
 * payment lands or an invoice is reconciled, fan the public-shaped payload out
 * to every active subscription of the owning user (Phase 5). Each event maps to
 * one public event name; the actual HTTP delivery is queued per subscription by
 * EventPublisher → DispatchWebhook.
 */
class PublishWebhookEvents
{
    public function __construct(private readonly EventPublisher $publisher) {}

    public function onPaymentReceived(PaymentReceived $event): void
    {
        $payment = $event->payment;
        $user = $payment->user ?? $payment->invoice?->user;

        if ($user !== null) {
            $this->publisher->publish(
                $user,
                'payment.received',
                WebhookPayload::forPayment($payment),
            );
        }
    }

    public function onInvoiceReconciled(InvoiceReconciled $event): void
    {
        $user = $event->invoice->user ?? $event->payment->user;

        if ($user !== null) {
            $this->publisher->publish(
                $user,
                'invoice.reconciled',
                WebhookPayload::forReconciliation($event->payment, $event->invoice),
            );
        }
    }
}
