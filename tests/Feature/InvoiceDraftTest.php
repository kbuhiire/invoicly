<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMatchStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Services\ForecastService;
use App\Services\PaymentReconciliationService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoiceDraftTest extends TestCase
{
    use RefreshDatabase;

    private function draftInvoice(User $user, array $attributes = []): Invoice
    {
        $client = Client::factory()->for($user)->external()->create();

        return Invoice::factory()->for($user)->for($client)->draft()->create(array_merge([
            'currency' => 'UGX',
            'amount' => '300.00',
        ], $attributes));
    }

    public function test_finalize_transitions_draft_and_stamps_sent_at(): void
    {
        $user = User::factory()->create();
        $invoice = $this->draftInvoice($user);

        $this->actingAs($user)
            ->from('/invoices')
            ->post("/invoices/{$invoice->uuid}/finalize")
            ->assertRedirect('/invoices');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::AwaitingPayment, $invoice->status);
        $this->assertNotNull($invoice->sent_at);
    }

    public function test_finalize_stamps_sent_at_on_already_finalized_invoice(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $invoice = Invoice::factory()->for($user)->for($client)->awaitingPayment()->create([
            'sent_at' => null,
        ]);

        $this->actingAs($user)->post("/invoices/{$invoice->uuid}/finalize");

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::AwaitingPayment, $invoice->status);
        $this->assertNotNull($invoice->sent_at);
    }

    public function test_finalize_is_forbidden_for_other_tenants(): void
    {
        $user = User::factory()->create();
        $other = $this->draftInvoice(User::factory()->create());

        $this->actingAs($user)->post("/invoices/{$other->uuid}/finalize")->assertForbidden();
    }

    public function test_finalized_invoice_cannot_be_reverted_to_draft(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $invoice = Invoice::factory()->for($user)->for($client)->awaitingPayment()->create();

        $response = $this->actingAs($user)->put("/invoices/{$invoice->uuid}", [
            'client_id' => $client->id,
            'issue_date' => now()->toDateString(),
            'status' => 'draft',
            'currency' => 'UGX',
            'line_items' => [
                ['description' => 'Work', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $response->assertSessionHasErrors(['status']);
        $this->assertSame(InvoiceStatus::AwaitingPayment, $invoice->refresh()->status);
    }

    public function test_payment_on_draft_is_rejected_via_web(): void
    {
        $user = User::factory()->create();
        $invoice = $this->draftInvoice($user);

        $this->actingAs($user)
            ->from('/invoices')
            ->post("/invoices/{$invoice->uuid}/payments", [
                'amount' => '100.00',
                'paid_at' => now()->toDateString(),
            ])
            ->assertSessionHasErrors(['invoice']);

        $this->assertSame(0, $invoice->payments()->count());
        $this->assertSame(InvoiceStatus::Draft, $invoice->refresh()->status);
    }

    public function test_payment_service_rejects_draft(): void
    {
        $user = User::factory()->create();
        $invoice = $this->draftInvoice($user);

        $this->expectException(ValidationException::class);

        app(PaymentService::class)->recordPayment($invoice, [
            'amount' => '100.00',
        ]);
    }

    public function test_recompute_leaves_draft_untouched(): void
    {
        $user = User::factory()->create();
        $invoice = $this->draftInvoice($user);

        app(PaymentService::class)->recompute($invoice);

        $this->assertSame(InvoiceStatus::Draft, $invoice->refresh()->status);
    }

    public function test_draft_is_excluded_from_dashboard_kpis(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        Invoice::factory()->for($user)->for($client)->draft()->create(['amount' => '900.00']);
        Invoice::factory()->for($user)->for($client)->awaitingPayment()->create([
            'amount' => '100.00',
            'amount_paid' => '0',
        ]);

        $this->actingAs($user)->get('/dashboard?segment=external')
            ->assertInertia(fn ($page) => $page
                ->where('kpis.outstanding', 100)
                ->where('status_breakdown.awaiting_count', 1));
    }

    public function test_draft_is_excluded_from_forecast(): void
    {
        $user = User::factory()->create();
        $this->draftInvoice($user, ['amount' => '500.00']);

        $forecast = app(ForecastService::class)->forUser($user);

        $this->assertSame(0.0, $forecast['total_expected']);
        $this->assertSame(0.0, $forecast['overdue_expected']);
    }

    public function test_draft_is_not_auto_matched_by_reconciliation(): void
    {
        $user = User::factory()->create();
        $invoice = $this->draftInvoice($user, ['amount' => '777.00', 'number' => 'EINV-2026-901']);

        $payment = app(PaymentReconciliationService::class)->ingest($user, [
            'amount' => '777.00',
            'currency' => 'UGX',
            'reference' => 'Paying EINV-2026-901',
            'external_id' => 'txn_draft_1',
        ]);

        $this->assertNull($payment->invoice_id);
        $this->assertNotSame(PaymentMatchStatus::Matched, $payment->match_status);
        $this->assertSame(InvoiceStatus::Draft, $invoice->refresh()->status);
    }

    public function test_invoice_index_balance_excludes_drafts(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        Invoice::factory()->for($user)->for($client)->draft()->create(['amount' => '900.00']);
        Invoice::factory()->for($user)->for($client)->awaitingPayment()->create([
            'amount' => '100.00',
            'amount_paid' => '0',
        ]);

        $this->actingAs($user)->get('/invoices?segment=external')
            ->assertInertia(fn ($page) => $page
                ->where('balance.awaiting_total', '100.00')
                ->has('invoices.data', 2));
    }

    public function test_status_filter_returns_only_drafts(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $draft = Invoice::factory()->for($user)->for($client)->draft()->create();
        Invoice::factory()->for($user)->for($client)->awaitingPayment()->create();

        $this->actingAs($user)->get('/invoices?segment=external&status=draft')
            ->assertInertia(fn ($page) => $page
                ->has('invoices.data', 1)
                ->where('invoices.data.0.uuid', $draft->uuid));
    }

    public function test_invoice_can_be_created_as_draft(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        $this->actingAs($user)->post('/invoices', [
            'client_id' => $client->id,
            'issue_date' => now()->toDateString(),
            'status' => 'draft',
            'currency' => 'UGX',
            'line_items' => [
                ['description' => 'Design work', 'quantity' => 2, 'unit_price' => 150],
            ],
        ]);

        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'amount' => '300.00',
        ]);
    }
}
