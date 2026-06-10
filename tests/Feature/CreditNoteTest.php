<?php

namespace Tests\Feature;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentSource;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CreditNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreditNoteTest extends TestCase
{
    use RefreshDatabase;

    private function openInvoice(User $user, Client $client, array $attributes = []): Invoice
    {
        return Invoice::factory()->for($user)->for($client)->awaitingPayment()->create(array_merge([
            'currency' => 'UGX',
            'amount' => '500.00',
            'amount_paid' => '0',
        ], $attributes));
    }

    public function test_issue_creates_numbered_credit_note(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        $this->actingAs($user)
            ->from('/credit-notes')
            ->post('/credit-notes', [
                'client_id' => $client->id,
                'currency' => 'ugx',
                'amount' => '150.00',
                'memo' => 'Overbilled',
            ]);

        $note = CreditNote::query()->firstOrFail();
        $this->assertSame('CN-'.now()->format('Y').'-1', $note->number);
        $this->assertSame(CreditNoteStatus::Issued, $note->status);
        $this->assertSame('UGX', $note->currency);
    }

    public function test_apply_settles_invoice_through_bridge_payment(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $invoice = $this->openInvoice($user, $client);

        $service = app(CreditNoteService::class);
        $note = $service->issue($user, [
            'client_id' => $client->id,
            'currency' => 'UGX',
            'amount' => '500.00',
        ]);

        $service->applyToInvoice($note, $invoice);

        $invoice->refresh();
        $note->refresh();

        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('500.00', $invoice->amount_paid);
        $this->assertSame(CreditNoteStatus::Applied, $note->status);
        $this->assertSame($invoice->id, $note->invoice_id);

        $payment = $note->payment;
        $this->assertNotNull($payment);
        $this->assertSame(PaymentSource::CreditNote, $payment->source);
        $this->assertSame($invoice->id, $payment->invoice_id);
    }

    public function test_partial_credit_leaves_invoice_partially_paid(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $invoice = $this->openInvoice($user, $client);

        $service = app(CreditNoteService::class);
        $note = $service->issue($user, ['client_id' => $client->id, 'currency' => 'UGX', 'amount' => '200.00']);
        $service->applyToInvoice($note, $invoice);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
        $this->assertSame('300.00', $invoice->outstandingAmount());
    }

    public function test_over_application_is_rejected(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $invoice = $this->openInvoice($user, $client, ['amount' => '100.00']);

        $service = app(CreditNoteService::class);
        $note = $service->issue($user, ['client_id' => $client->id, 'currency' => 'UGX', 'amount' => '150.00']);

        $this->expectException(ValidationException::class);
        $service->applyToInvoice($note, $invoice);
    }

    public function test_currency_mismatch_is_rejected(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $invoice = $this->openInvoice($user, $client, ['currency' => 'USD']);

        $service = app(CreditNoteService::class);
        $note = $service->issue($user, ['client_id' => $client->id, 'currency' => 'UGX', 'amount' => '50.00']);

        $this->expectException(ValidationException::class);
        $service->applyToInvoice($note, $invoice);
    }

    public function test_apply_to_draft_is_rejected(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $draft = Invoice::factory()->for($user)->for($client)->draft()->create([
            'currency' => 'UGX',
            'amount' => '500.00',
        ]);

        $service = app(CreditNoteService::class);
        $note = $service->issue($user, ['client_id' => $client->id, 'currency' => 'UGX', 'amount' => '100.00']);

        $this->expectException(ValidationException::class);
        $service->applyToInvoice($note, $draft);
    }

    public function test_void_restores_invoice_state(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $invoice = $this->openInvoice($user, $client);

        $service = app(CreditNoteService::class);
        $note = $service->issue($user, ['client_id' => $client->id, 'currency' => 'UGX', 'amount' => '500.00']);
        $service->applyToInvoice($note, $invoice);

        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);

        $service->void($note->refresh());

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::AwaitingPayment, $invoice->status);
        $this->assertSame('0.00', $invoice->amount_paid);
        $this->assertSame(CreditNoteStatus::Void, $note->refresh()->status);
        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_applied_note_cannot_be_applied_again(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $invoiceA = $this->openInvoice($user, $client, ['amount' => '100.00']);
        $invoiceB = $this->openInvoice($user, $client, ['amount' => '100.00']);

        $service = app(CreditNoteService::class);
        $note = $service->issue($user, ['client_id' => $client->id, 'currency' => 'UGX', 'amount' => '100.00']);
        $service->applyToInvoice($note, $invoiceA);

        $this->expectException(ValidationException::class);
        $service->applyToInvoice($note->refresh(), $invoiceB);
    }

    public function test_web_endpoints_are_tenant_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherClient = Client::factory()->for($other)->external()->create();
        $note = app(CreditNoteService::class)->issue($other, [
            'client_id' => $otherClient->id,
            'currency' => 'UGX',
            'amount' => '10.00',
        ]);

        $this->actingAs($user)->post("/credit-notes/{$note->uuid}/void")->assertForbidden();
        $this->actingAs($user)->get("/credit-notes/{$note->uuid}/pdf")->assertForbidden();

        $this->actingAs($user)->post('/credit-notes', [
            'client_id' => $otherClient->id,
            'currency' => 'UGX',
            'amount' => '10.00',
        ])->assertSessionHasErrors(['client_id']);
    }

    public function test_dashboard_revenue_unchanged_by_credit_application(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $invoice = $this->openInvoice($user, $client, ['amount' => '500.00']);

        $service = app(CreditNoteService::class);
        $note = $service->issue($user, ['client_id' => $client->id, 'currency' => 'UGX', 'amount' => '500.00']);
        $service->applyToInvoice($note, $invoice);

        // Revenue sums invoice amounts where status=paid; the credited invoice
        // counts as settled, which is the documented behaviour.
        $this->actingAs($user)->get('/dashboard?segment=external')
            ->assertInertia(fn ($page) => $page
                ->where('kpis.outstanding', 0)
                ->where('kpis.total_revenue', 500));
    }

    public function test_index_page_renders_with_open_invoices(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $this->openInvoice($user, $client);

        $this->actingAs($user)->get('/credit-notes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CreditNotes/Index')
                ->has('openInvoices', 1));
    }
}
