<?php

namespace App\Jobs;

use App\Models\WebhookSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Delivers a single signed event payload to one subscription. Retried with
 * backoff on transport errors or non-2xx responses; the receiver verifies
 * authenticity via the X-Invoicly-Signature header:
 *
 *   signature = hash_hmac('sha256', rawBody, subscription.secret)
 */
class DispatchWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 15;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly WebhookSubscription $subscription,
        public readonly string $event,
        public readonly array $payload,
    ) {}

    /**
     * Exponential-ish backoff between retries (seconds).
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(): void
    {
        if (! $this->subscription->active) {
            return;
        }

        $body = json_encode([
            'event' => $this->event,
            'created_at' => now()->toIso8601String(),
            'data' => $this->payload,
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $body, $this->subscription->secret);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Invoicly-Event' => $this->event,
            'X-Invoicly-Signature' => $signature,
            'X-Invoicly-Delivery' => (string) Str::uuid(),
        ])->withBody($body, 'application/json')->post($this->subscription->url);

        $this->subscription->forceFill([
            'last_status' => $response->status(),
            'last_dispatched_at' => now(),
        ])->save();

        if ($response->successful()) {
            if ($this->subscription->failure_count > 0) {
                $this->subscription->forceFill(['failure_count' => 0])->save();
            }

            return;
        }

        $this->subscription->increment('failure_count');

        // Non-2xx -> throw so the queue retries with backoff.
        throw new RuntimeException(
            "Webhook to {$this->subscription->url} returned HTTP {$response->status()}."
        );
    }
}
