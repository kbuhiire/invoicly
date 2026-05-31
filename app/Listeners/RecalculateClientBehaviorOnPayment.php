<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Services\ClientBehaviorService;

/**
 * Keep a client's cached behaviour/credit aggregates fresh the moment a payment
 * lands, so credit insights and forecasting don't have to wait for the nightly
 * recompute. Unmatched payments (no invoice → no client) are skipped.
 */
class RecalculateClientBehaviorOnPayment
{
    public function __construct(private readonly ClientBehaviorService $behavior) {}

    public function handle(PaymentReceived $event): void
    {
        $client = $event->payment->invoice?->client;

        if ($client !== null) {
            $this->behavior->recalculate($client);
        }
    }
}
