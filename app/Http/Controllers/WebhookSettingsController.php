<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Models\WebhookSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Self-service management for the Phase 5 developer surface:
 *   - Integrations  → inbound endpoints external systems POST payment events to.
 *   - Webhook subscriptions → outbound URLs we POST signed event payloads to.
 *
 * Both carry a signing secret shown exactly once at creation (it is otherwise
 * hidden from serialization), mirroring the API-token flow.
 */
class WebhookSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $integrations = $user->integrations()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Integration $i) => [
                'id' => $i->uuid,
                'name' => $i->name,
                'provider' => $i->provider,
                'active' => $i->active,
                'endpoint' => route('webhooks.incoming.payments', $i->uuid),
                'last_event_at' => $i->last_event_at?->diffForHumans(),
                'created_at' => $i->created_at->format('M j, Y'),
            ]);

        $subscriptions = $user->webhookSubscriptions()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (WebhookSubscription $s) => [
                'id' => $s->uuid,
                'url' => $s->url,
                'events' => $s->events,
                'active' => $s->active,
                'failure_count' => $s->failure_count,
                'last_status' => $s->last_status,
                'last_dispatched_at' => $s->last_dispatched_at?->diffForHumans(),
                'created_at' => $s->created_at->format('M j, Y'),
            ]);

        return Inertia::render('Settings/Webhooks', [
            'integrations' => $integrations,
            'subscriptions' => $subscriptions,
            'availableEvents' => WebhookSubscription::EVENTS,
            'newIntegration' => session('new_integration'),
            'newSubscription' => session('new_subscription'),
        ]);
    }

    public function storeIntegration(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'provider' => ['required', 'string', 'max:40'],
        ]);

        $secret = Integration::generateSecret();

        $integration = $request->user()->integrations()->create([
            'name' => $data['name'],
            'provider' => $data['provider'],
            'signing_secret' => $secret,
            'active' => true,
        ]);

        return redirect()
            ->route('settings.webhooks.index')
            ->with('new_integration', [
                'name' => $integration->name,
                'endpoint' => route('webhooks.incoming.payments', $integration->uuid),
                'signing_secret' => $secret,
            ]);
    }

    public function destroyIntegration(Request $request, Integration $integration): RedirectResponse
    {
        abort_unless($integration->user_id === $request->user()->id, 403);

        $integration->delete();

        return redirect()
            ->route('settings.webhooks.index')
            ->with('success', 'Integration deleted.');
    }

    public function storeSubscription(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in(WebhookSubscription::EVENTS)],
        ]);

        $secret = WebhookSubscription::generateSecret();

        $subscription = $request->user()->webhookSubscriptions()->create([
            'url' => $data['url'],
            'events' => array_values(array_unique($data['events'])),
            'secret' => $secret,
            'active' => true,
        ]);

        return redirect()
            ->route('settings.webhooks.index')
            ->with('new_subscription', [
                'url' => $subscription->url,
                'secret' => $secret,
            ]);
    }

    public function destroySubscription(Request $request, WebhookSubscription $subscription): RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->id, 403);

        $subscription->delete();

        return redirect()
            ->route('settings.webhooks.index')
            ->with('success', 'Webhook subscription deleted.');
    }
}
