<?php

namespace App\Services\Webhooks;

use App\Jobs\DispatchWebhook;
use App\Models\User;

/**
 * Fans a domain event out to every active webhook subscription of a user that
 * listens for it, queueing one delivery job per subscription. Building the
 * payload is the caller's job; this only handles routing + dispatch.
 */
class EventPublisher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(User $user, string $event, array $payload): void
    {
        $user->webhookSubscriptions()
            ->where('active', true)
            ->get()
            ->filter(fn ($subscription) => $subscription->subscribesTo($event))
            ->each(fn ($subscription) => DispatchWebhook::dispatch($subscription, $event, $payload));
    }
}
