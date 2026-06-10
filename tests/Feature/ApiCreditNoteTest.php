<?php

namespace Tests\Feature;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CreditNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiCreditNoteTest extends TestCase
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

    private function issuedNote(User $user, Client $client, array $attributes = []): CreditNote
    {
        return app(CreditNoteService::class)->issue($user, array_merge([
            'client_id' => $client->id,
            'currency' => 'UGX',
            'amount' => '500.00',
        ], $attributes));
    }

    public function test_listing_credit_notes_requires_read_ability(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['credit-notes:write']);

        $this->getJson('/api/v1/credit-notes')->assertForbidden();
    }

    public function test_mutations_require_write_ability(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $note = $this->issuedNote($user, $client);

        Sanctum::actingAs($user, ['credit-notes:read']);

        // Valid payload: the FormRequest passes, then the ability guard fires.
        $this->postJson('/api/v1/credit-notes', [
            'client_id' => $client->id,
            'currency' => 'UGX',
            'amount' => '100.00',
        ])->assertForbidden();
        $this->postJson("/api/v1/credit-notes/{$note->uuid}/apply", ['invoice_id' => 1])->assertForbidden();
        $this->postJson("/api/v1/credit-notes/{$note->uuid}/void")->assertForbidden();
    }

    public function test_list_is_scoped_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $mine = $this->issuedNote($user, $client);

        $other = User::factory()->create();
        $otherClient = Client::factory()->for($other)->external()->create();
        $this->issuedNote($other, $otherClient);

        Sanctum::actingAs($user, ['credit-notes:read']);

        $this->getJson('/api/v1/credit-notes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', $mine->number);
    }

    public function test_show_forbids_foreign_credit_note(): void
    {
        $other = User::factory()->create();
        $otherClient = Client::factory()->for($other)->external()->create();
        $foreign = $this->issuedNote($other, $otherClient);

        Sanctum::actingAs(User::factory()->create(), ['credit-notes:read']);

        $this->getJson("/api/v1/credit-notes/{$foreign->uuid}")->assertForbidden();
    }

    public function test_status_filter_limits_results(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        $issued = $this->issuedNote($user, $client, ['amount' => '100.00']);
        $voided = $this->issuedNote($user, $client, ['amount' => '50.00']);
        app(CreditNoteService::class)->void($voided);

        Sanctum::actingAs($user, ['credit-notes:read']);

        $this->getJson('/api/v1/credit-notes?status=issued')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', $issued->number)
            ->assertJsonPath('data.0.status', 'issued');
    }

    public function test_store_issues_credit_note_with_cn_numbering(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        Sanctum::actingAs($user, ['credit-notes:write']);

        $this->postJson('/api/v1/credit-notes', [
            'client_id' => $client->id,
            'currency' => 'ugx',
            'amount' => '150.00',
            'memo' => 'Overbilled',
        ])->assertCreated()
            ->assertJsonPath('data.number', 'CN-'.now()->format('Y').'-1')
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.currency', 'UGX')
            ->assertJsonPath('data.amount', 150);

        $this->assertDatabaseHas('credit_notes', [
            'user_id' => $user->id,
            'number' => 'CN-'.now()->format('Y').'-1',
            'status' => CreditNoteStatus::Issued->value,
        ]);
    }

    public function test_store_validation_errors_return_422(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['credit-notes:write']);

        $this->postJson('/api/v1/credit-notes', [
            'amount' => '-5',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['client_id', 'currency', 'amount']);
    }

    public function test_apply_settles_invoice_through_bridge_payment(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $invoice = $this->openInvoice($user, $client);
        $note = $this->issuedNote($user, $client);

        Sanctum::actingAs($user, ['credit-notes:write']);

        $this->postJson("/api/v1/credit-notes/{$note->uuid}/apply", [
            'invoice_id' => $invoice->id,
        ])->assertOk()
            ->assertJsonPath('data.status', 'applied')
            ->assertJsonPath('data.invoice_id', $invoice->id);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('500.00', $invoice->amount_paid);
    }

    public function test_apply_rejects_currency_mismatch_with_422(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $invoice = $this->openInvoice($user, $client, ['currency' => 'USD']);
        $note = $this->issuedNote($user, $client);

        Sanctum::actingAs($user, ['credit-notes:write']);

        $this->postJson("/api/v1/credit-notes/{$note->uuid}/apply", [
            'invoice_id' => $invoice->id,
        ])->assertStatus(422);

        $this->assertSame(CreditNoteStatus::Issued, $note->refresh()->status);
    }

    public function test_void_restores_the_invoice(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $invoice = $this->openInvoice($user, $client);
        $note = $this->issuedNote($user, $client);

        app(CreditNoteService::class)->applyToInvoice($note, $invoice);
        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);

        Sanctum::actingAs($user, ['credit-notes:write']);

        $this->postJson("/api/v1/credit-notes/{$note->uuid}/void")
            ->assertOk()
            ->assertJsonPath('data.status', 'void');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::AwaitingPayment, $invoice->status);
        $this->assertSame('0.00', $invoice->amount_paid);
    }

    public function test_voiding_an_already_void_note_returns_422(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $note = $this->issuedNote($user, $client);
        app(CreditNoteService::class)->void($note);

        Sanctum::actingAs($user, ['credit-notes:write']);

        $this->postJson("/api/v1/credit-notes/{$note->uuid}/void")->assertStatus(422);
    }
}
