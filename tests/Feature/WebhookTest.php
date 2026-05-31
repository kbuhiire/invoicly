<?php

namespace Tests\Feature;

use App\Enums\PaymentMatchStatus;
use App\Events\InvoiceReconciled;
use App\Events\PaymentReceived;
use App\Jobs\DispatchWebhook;
use App\Models\Client;
use App\Models\Integration;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReminderLog;
use App\Models\User;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function openInvoice(User $user, array $overrides = []): Invoice
    {
        $client = Client::factory()->for($user)->external()->create();

        return Invoice::factory()->for($user)->for($client)->awaitingPayment()->create(array_merge([
            'currency' => 'UGX',
            'amount' => '250.00',
            'amount_paid' => '0',
        ], $overrides));
    }

    private function integration(User $user, array $overrides = []): Integration
    {
        return $user->integrations()->create(array_merge([
            'provider' => 'generic',
            'name' => 'Test gateway',
            'signing_secret' => Integration::generateSecret(),
            'active' => true,
        ], $overrides));
    }

    /**
     * POST a JSON body to an inbound integration endpoint with a valid (or
     * overridden) HMAC signature, signing the exact bytes that are sent.
     */
    private function postSignedPayment(Integration $integration, array $data, ?string $signature = null)
    {
        $raw = json_encode($data);
        $signature ??= hash_hmac('sha256', $raw, $integration->signing_secret);

        return $this->call(
            'POST',
            "/webhooks/incoming/{$integration->uuid}/payments",
            [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_INVOICLY_SIGNATURE' => $signature,
            ],
            $raw,
        );
    }

    private function twilioSignature(string $token, string $url, array $params): string
    {
        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        return base64_encode(hash_hmac('sha1', $data, $token, true));
    }

    // ── Inbound: incoming payments ────────────────────────────────────────────

    public function test_inbound_payment_with_valid_signature_is_reconciled(): void
    {
        $user = User::factory()->create();
        $invoice = $this->openInvoice($user, ['number' => 'EINV-2026-7']);
        $integration = $this->integration($user);

        $response = $this->postSignedPayment($integration, [
            'amount' => '250.00',
            'currency' => 'UGX',
            'reference' => 'Payment for invoice EINV-2026-7',
            'external_id' => 'txn_001',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('match_status', PaymentMatchStatus::Matched->value)
            ->assertJsonPath('invoice_id', $invoice->uuid);

        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'external_id' => 'txn_001',
            'invoice_id' => $invoice->id,
        ]);

        $this->assertNotNull($integration->refresh()->last_event_at);
    }

    public function test_inbound_payment_with_invalid_signature_is_rejected(): void
    {
        $user = User::factory()->create();
        $integration = $this->integration($user);

        $this->postSignedPayment($integration, [
            'amount' => '99.00',
            'currency' => 'UGX',
            'external_id' => 'txn_bad',
        ], signature: 'deadbeef')->assertStatus(401);

        $this->assertDatabaseMissing('payments', ['external_id' => 'txn_bad']);
    }

    public function test_inbound_payment_to_inactive_integration_is_not_found(): void
    {
        $user = User::factory()->create();
        $integration = $this->integration($user, ['active' => false]);

        // Even with a valid signature, an inactive integration is hidden (404).
        $this->postSignedPayment($integration, [
            'amount' => '10.00',
            'currency' => 'UGX',
        ])->assertNotFound();
    }

    public function test_inbound_payment_to_unknown_integration_is_not_found(): void
    {
        $this->call(
            'POST',
            '/webhooks/incoming/00000000-0000-0000-0000-000000000000/payments',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_INVOICLY_SIGNATURE' => 'x'],
            json_encode(['amount' => '1.00', 'currency' => 'UGX']),
        )->assertNotFound();
    }

    public function test_inbound_payment_is_idempotent_on_external_id(): void
    {
        $user = User::factory()->create();
        $this->openInvoice($user, ['number' => 'EINV-2026-9']);
        $integration = $this->integration($user);

        $payload = [
            'amount' => '250.00',
            'currency' => 'UGX',
            'reference' => 'EINV-2026-9',
            'external_id' => 'dup_1',
        ];

        $this->postSignedPayment($integration, $payload)->assertStatus(202);
        $this->postSignedPayment($integration, $payload)->assertStatus(202);

        $this->assertSame(1, Payment::where('external_id', 'dup_1')->count());
    }

    // ── Inbound: Twilio status callbacks ──────────────────────────────────────

    public function test_twilio_status_callback_marks_reminder_failed(): void
    {
        config(['services.twilio.token' => 'test_token']);

        $user = User::factory()->create();
        $invoice = $this->openInvoice($user);
        $log = ReminderLog::create([
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'type' => 'overdue',
            'dedupe_key' => 'overdue:1',
            'channel' => 'sms',
            'status' => ReminderLog::STATUS_SENT,
            'provider_message_sid' => 'SM_TEST_1',
        ]);

        $url = url('/webhooks/twilio/status');
        $params = ['MessageSid' => 'SM_TEST_1', 'MessageStatus' => 'undelivered'];
        $signature = $this->twilioSignature('test_token', $url, $params);

        $this->post('/webhooks/twilio/status', $params, ['X-Twilio-Signature' => $signature])
            ->assertNoContent();

        $log->refresh();
        $this->assertSame(ReminderLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('undelivered', (string) $log->error);
    }

    public function test_twilio_status_callback_with_bad_signature_is_forbidden(): void
    {
        config(['services.twilio.token' => 'test_token']);

        $this->post('/webhooks/twilio/status', ['MessageSid' => 'x', 'MessageStatus' => 'delivered'], [
            'X-Twilio-Signature' => 'not-valid',
        ])->assertForbidden();
    }

    // ── Outbound: event fan-out ───────────────────────────────────────────────

    public function test_payment_received_dispatches_webhook_to_subscribers(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $invoice = $this->openInvoice($user);
        $payment = Payment::factory()->for($user)->for($invoice)->create();

        $subscription = $user->webhookSubscriptions()->create([
            'url' => 'https://example.com/hook',
            'secret' => 'whsec_test',
            'events' => ['payment.received'],
            'active' => true,
        ]);

        event(new PaymentReceived($payment));

        Bus::assertDispatched(DispatchWebhook::class, function (DispatchWebhook $job) use ($subscription) {
            return $job->event === 'payment.received'
                && $job->subscription->is($subscription);
        });
    }

    public function test_invoice_reconciled_dispatches_webhook_to_subscribers(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $invoice = $this->openInvoice($user);
        $payment = Payment::factory()->for($user)->for($invoice)->create();

        $user->webhookSubscriptions()->create([
            'url' => 'https://example.com/hook',
            'secret' => 'whsec_test',
            'events' => ['invoice.reconciled'],
            'active' => true,
        ]);

        event(new InvoiceReconciled($payment, $invoice));

        Bus::assertDispatched(DispatchWebhook::class, fn (DispatchWebhook $job) => $job->event === 'invoice.reconciled');
    }

    public function test_subscription_not_listening_for_event_is_skipped(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $invoice = $this->openInvoice($user);
        $payment = Payment::factory()->for($user)->for($invoice)->create();

        $user->webhookSubscriptions()->create([
            'url' => 'https://example.com/hook',
            'secret' => 'whsec_test',
            'events' => ['invoice.reconciled'], // not payment.received
            'active' => true,
        ]);

        event(new PaymentReceived($payment));

        Bus::assertNotDispatched(DispatchWebhook::class);
    }

    public function test_inactive_subscription_is_skipped(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $invoice = $this->openInvoice($user);
        $payment = Payment::factory()->for($user)->for($invoice)->create();

        $user->webhookSubscriptions()->create([
            'url' => 'https://example.com/hook',
            'secret' => 'whsec_test',
            'events' => ['payment.received'],
            'active' => false,
        ]);

        event(new PaymentReceived($payment));

        Bus::assertNotDispatched(DispatchWebhook::class);
    }

    // ── Outbound: delivery job ────────────────────────────────────────────────

    public function test_dispatch_webhook_posts_a_signed_payload(): void
    {
        Http::fake(['https://example.com/*' => Http::response('', 200)]);

        $user = User::factory()->create();
        $subscription = $user->webhookSubscriptions()->create([
            'url' => 'https://example.com/hook',
            'secret' => 'whsec_known',
            'events' => ['payment.received'],
            'active' => true,
        ]);

        (new DispatchWebhook($subscription, 'payment.received', ['id' => 'abc', 'amount' => 250.0]))->handle();

        Http::assertSent(function ($request) {
            $expected = hash_hmac('sha256', $request->body(), 'whsec_known');

            return $request->url() === 'https://example.com/hook'
                && $request->hasHeader('X-Invoicly-Event', 'payment.received')
                && $request->hasHeader('X-Invoicly-Signature', $expected);
        });

        $this->assertSame(200, $subscription->refresh()->last_status);
        $this->assertSame(0, $subscription->failure_count);
    }

    public function test_dispatch_webhook_throws_to_retry_on_non_2xx(): void
    {
        Http::fake(['https://example.com/*' => Http::response('nope', 500)]);

        $user = User::factory()->create();
        $subscription = $user->webhookSubscriptions()->create([
            'url' => 'https://example.com/hook',
            'secret' => 'whsec_known',
            'events' => ['payment.received'],
            'active' => true,
        ]);

        try {
            (new DispatchWebhook($subscription, 'payment.received', ['id' => 'abc']))->handle();
            $this->fail('Expected a RuntimeException on non-2xx response.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(1, $subscription->refresh()->failure_count);
        $this->assertSame(500, $subscription->last_status);
    }

    // ── Management: web ───────────────────────────────────────────────────────

    public function test_user_can_create_integration_via_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('settings.integrations.store'), [
                'name' => 'Stripe',
                'provider' => 'stripe',
            ])
            ->assertRedirect(route('settings.webhooks.index'))
            ->assertSessionHas('new_integration');

        $this->assertDatabaseHas('integrations', [
            'user_id' => $user->id,
            'name' => 'Stripe',
            'provider' => 'stripe',
        ]);
    }

    public function test_user_can_create_subscription_via_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('settings.webhook-subscriptions.store'), [
                'url' => 'https://example.com/hook',
                'events' => ['payment.received'],
            ])
            ->assertRedirect(route('settings.webhooks.index'))
            ->assertSessionHas('new_subscription');

        $this->assertDatabaseHas('webhook_subscriptions', [
            'user_id' => $user->id,
            'url' => 'https://example.com/hook',
        ]);
    }

    public function test_user_cannot_delete_another_users_integration(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $integration = $this->integration($owner);

        $this->actingAs($other)
            ->delete(route('settings.integrations.destroy', $integration->uuid))
            ->assertForbidden();

        $this->assertDatabaseHas('integrations', ['id' => $integration->id]);
    }

    // ── Management: API ───────────────────────────────────────────────────────

    public function test_api_create_subscription_requires_manage_ability(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['webhooks:read']);

        $this->postJson('/api/v1/webhooks/subscriptions', [
            'url' => 'https://example.com/hook',
            'events' => ['payment.received'],
        ])->assertForbidden();
    }

    public function test_api_create_subscription_returns_secret_once(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['webhooks:manage']);

        $this->postJson('/api/v1/webhooks/subscriptions', [
            'url' => 'https://example.com/hook',
            'events' => ['payment.received', 'invoice.reconciled'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.url', 'https://example.com/hook')
            ->assertJsonStructure(['data' => ['id', 'url', 'events', 'secret']]);

        $this->assertDatabaseHas('webhook_subscriptions', [
            'user_id' => $user->id,
            'url' => 'https://example.com/hook',
        ]);
    }

    public function test_api_list_subscriptions_hides_secret(): void
    {
        $user = User::factory()->create();
        $user->webhookSubscriptions()->create([
            'url' => 'https://example.com/hook',
            'secret' => 'whsec_hidden',
            'events' => ['payment.received'],
            'active' => true,
        ]);

        Sanctum::actingAs($user, ['webhooks:read']);

        $this->getJson('/api/v1/webhooks/subscriptions')
            ->assertOk()
            ->assertJsonMissing(['secret' => 'whsec_hidden'])
            ->assertJsonPath('data.0.url', 'https://example.com/hook');
    }

    public function test_api_cannot_delete_another_users_subscription(): void
    {
        $owner = User::factory()->create();
        $subscription = $owner->webhookSubscriptions()->create([
            'url' => 'https://example.com/hook',
            'secret' => 'whsec_x',
            'events' => ['payment.received'],
            'active' => true,
        ]);

        $other = User::factory()->create();
        Sanctum::actingAs($other, ['webhooks:manage']);

        $this->deleteJson("/api/v1/webhooks/subscriptions/{$subscription->uuid}")
            ->assertNotFound();

        $this->assertDatabaseHas('webhook_subscriptions', ['id' => $subscription->id]);
    }
}
