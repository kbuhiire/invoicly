<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMatchStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function openInvoice(User $user, array $overrides = []): Invoice
    {
        $client = Client::factory()->for($user)->external()->create($overrides['client'] ?? []);

        return Invoice::factory()->for($user)->for($client)->awaitingPayment()->create(array_merge([
            'currency' => 'UGX',
            'amount' => '250.00',
            'amount_paid' => '0',
        ], $overrides['invoice'] ?? []));
    }

    public function test_payment_referencing_invoice_number_is_auto_matched(): void
    {
        $user = User::factory()->create();
        $invoice = $this->openInvoice($user, ['invoice' => ['number' => 'EINV-2026-7']]);

        Sanctum::actingAs($user, ['payments:write']);

        $response = $this->postJson('/api/v1/payments', [
            'amount' => '250.00',
            'currency' => 'UGX',
            'reference' => 'Payment for invoice EINV-2026-7, thanks',
            'external_id' => 'txn_001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.match_status', PaymentMatchStatus::Matched->value)
            ->assertJsonPath('data.invoice_id', $invoice->id);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('250.00', $invoice->amount_paid);
    }

    public function test_exact_amount_on_single_open_invoice_is_auto_matched(): void
    {
        $user = User::factory()->create();
        $invoice = $this->openInvoice($user, ['invoice' => ['amount' => '999.00']]);

        Sanctum::actingAs($user, ['payments:write']);

        $this->postJson('/api/v1/payments', [
            'amount' => '999.00',
            'currency' => 'UGX',
            'reference' => 'bank transfer',
        ])->assertCreated()->assertJsonPath('data.invoice_id', $invoice->id);

        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
    }

    public function test_amount_matching_multiple_invoices_goes_to_review(): void
    {
        $user = User::factory()->create();
        $this->openInvoice($user, ['invoice' => ['amount' => '500.00']]);
        $this->openInvoice($user, ['invoice' => ['amount' => '500.00']]);

        Sanctum::actingAs($user, ['payments:write']);

        $this->postJson('/api/v1/payments', [
            'amount' => '500.00',
            'currency' => 'UGX',
        ])->assertCreated()
            ->assertJsonPath('data.match_status', PaymentMatchStatus::Review->value)
            ->assertJsonPath('data.invoice_id', null);
    }

    public function test_payment_with_no_signal_is_unmatched(): void
    {
        $user = User::factory()->create();
        $this->openInvoice($user, ['invoice' => ['amount' => '250.00']]);

        Sanctum::actingAs($user, ['payments:write']);

        $this->postJson('/api/v1/payments', [
            'amount' => '17.50',
            'currency' => 'UGX',
        ])->assertCreated()
            ->assertJsonPath('data.match_status', PaymentMatchStatus::Unmatched->value)
            ->assertJsonPath('data.invoice_id', null);
    }

    public function test_known_payer_routes_to_review(): void
    {
        $user = User::factory()->create();
        $this->openInvoice($user, [
            'client' => ['email' => 'pays@acme.test'],
            'invoice' => ['amount' => '250.00'],
        ]);

        Sanctum::actingAs($user, ['payments:write']);

        $this->postJson('/api/v1/payments', [
            'amount' => '60.00',
            'currency' => 'UGX',
            'payer_email' => 'pays@acme.test',
        ])->assertCreated()
            ->assertJsonPath('data.match_status', PaymentMatchStatus::Review->value);
    }

    public function test_duplicate_external_id_is_idempotent(): void
    {
        $user = User::factory()->create();
        $this->openInvoice($user, ['invoice' => ['number' => 'EINV-2026-9']]);

        Sanctum::actingAs($user, ['payments:write']);

        $payload = [
            'amount' => '250.00',
            'currency' => 'UGX',
            'reference' => 'EINV-2026-9',
            'external_id' => 'dup_123',
        ];

        $first = $this->postJson('/api/v1/payments', $payload)->assertCreated();
        $this->postJson('/api/v1/payments', $payload)->assertSuccessful();

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(
            $first->json('data.id'),
            Payment::query()->first()->id,
        );
    }

    public function test_currency_mismatch_on_referenced_invoice_goes_to_review(): void
    {
        $user = User::factory()->create();
        $this->openInvoice($user, ['invoice' => ['number' => 'EINV-2026-3', 'currency' => 'UGX']]);

        Sanctum::actingAs($user, ['payments:write']);

        $this->postJson('/api/v1/payments', [
            'amount' => '250.00',
            'currency' => 'USD',
            'reference' => 'EINV-2026-3',
        ])->assertCreated()
            ->assertJsonPath('data.match_status', PaymentMatchStatus::Review->value);
    }

    public function test_token_without_payments_write_ability_is_forbidden(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['invoices:read']);

        $this->postJson('/api/v1/payments', [
            'amount' => '10.00',
            'currency' => 'UGX',
        ])->assertForbidden();
    }

    public function test_explicit_endpoint_records_against_invoice(): void
    {
        $user = User::factory()->create();
        $invoice = $this->openInvoice($user, ['invoice' => ['amount' => '400.00']]);

        Sanctum::actingAs($user, ['payments:write']);

        $this->postJson("/api/v1/invoices/{$invoice->uuid}/payments", [
            'amount' => '400.00',
            'currency' => 'UGX',
        ])->assertCreated()->assertJsonPath('data.invoice_id', $invoice->id);

        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
    }

    public function test_user_can_manually_match_a_review_payment(): void
    {
        $user = User::factory()->create();
        $invoice = $this->openInvoice($user, ['invoice' => ['amount' => '500.00']]);
        // Two invoices share the amount -> lands in review.
        $this->openInvoice($user, ['invoice' => ['amount' => '500.00']]);

        Sanctum::actingAs($user, ['payments:write']);
        $this->postJson('/api/v1/payments', ['amount' => '500.00', 'currency' => 'UGX'])->assertCreated();

        $payment = Payment::query()->where('match_status', PaymentMatchStatus::Review->value)->firstOrFail();

        $this->actingAs($user)
            ->post(route('reconciliation.match', $payment->uuid), ['invoice_id' => $invoice->id])
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame(PaymentMatchStatus::Matched, $payment->match_status);
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
    }

    public function test_reconciliation_page_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reconciliation.index'))
            ->assertOk();
    }
}
