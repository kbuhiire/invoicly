<?php

namespace App\Http\Middleware;

use App\Models\Integration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates inbound webhooks with an HMAC-SHA256 over the raw request body,
 * keyed by the integration's signing secret. This intentionally does NOT use
 * Sanctum — the caller is an external system identified by the integration uuid
 * in the URL, not a logged-in user.
 *
 * The sender must include the hex digest in the X-Invoicly-Signature header:
 *   signature = hash_hmac('sha256', rawBody, integration.signing_secret)
 */
class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $integration = $request->route('integration');

        if (! $integration instanceof Integration || ! $integration->active) {
            abort(404);
        }

        $provided = (string) $request->header('X-Invoicly-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), $integration->signing_secret);

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            abort(401, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}
