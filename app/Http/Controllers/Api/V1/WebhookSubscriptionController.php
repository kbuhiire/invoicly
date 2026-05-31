<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebhookSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * API-first management of outbound webhook subscriptions (Phase 5). Mirrors the
 * web settings surface so integrations can self-register endpoints. The signing
 * secret is returned exactly once, on creation.
 */
class WebhookSubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan('webhooks:read'), 403, 'Token missing webhooks:read ability.');

        $data = $request->user()->webhookSubscriptions()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (WebhookSubscription $s) => $this->summary($s));

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan('webhooks:manage'), 403, 'Token missing webhooks:manage ability.');

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

        // `secret` is hidden from serialization, so surface it once here.
        return response()->json([
            'data' => [...$this->summary($subscription), 'secret' => $secret],
        ], 201);
    }

    public function destroy(Request $request, WebhookSubscription $subscription): Response
    {
        abort_unless($request->user()->tokenCan('webhooks:manage'), 403, 'Token missing webhooks:manage ability.');
        abort_unless($subscription->user_id === $request->user()->id, 404);

        $subscription->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(WebhookSubscription $subscription): array
    {
        return [
            'id' => $subscription->uuid,
            'url' => $subscription->url,
            'events' => $subscription->events,
            'active' => $subscription->active,
            'failure_count' => $subscription->failure_count,
            'last_status' => $subscription->last_status,
            'last_dispatched_at' => $subscription->last_dispatched_at?->toIso8601String(),
            'created_at' => $subscription->created_at->toIso8601String(),
        ];
    }
}
