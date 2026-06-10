<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiQuoteTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuote(User $user, Client $client, array $attributes = []): Quote
    {
        $quote = Quote::query()->create(array_merge([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'number' => 'QUO-'.now()->format('Y').'-'.fake()->unique()->numberBetween(1, 9999),
            'status' => QuoteStatus::Draft,
            'issue_date' => now()->toDateString(),
            'currency' => 'UGX',
            'amount' => '300.00',
        ], $attributes));

        $quote->lineItems()->create([
            'description' => 'Design work',
            'quantity' => 2,
            'unit_price' => 150,
            'sort_order' => 0,
        ]);

        return $quote;
    }

    public function test_listing_quotes_requires_read_ability(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['quotes:write']);

        $this->getJson('/api/v1/quotes')->assertForbidden();
    }

    public function test_mutations_require_write_ability(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $quote = $this->makeQuote($user, $client);

        Sanctum::actingAs($user, ['quotes:read']);

        // Valid payload: the FormRequest passes, then the ability guard fires.
        $this->postJson('/api/v1/quotes', [
            'client_id' => $client->id,
            'issue_date' => now()->toDateString(),
            'currency' => 'UGX',
            'line_items' => [
                ['description' => 'Work', 'quantity' => 1, 'unit_price' => 100],
            ],
        ])->assertForbidden();
        $this->deleteJson("/api/v1/quotes/{$quote->uuid}")->assertForbidden();
        $this->postJson("/api/v1/quotes/{$quote->uuid}/convert")->assertForbidden();
    }

    public function test_list_is_scoped_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $mine = $this->makeQuote($user, $client);

        $other = User::factory()->create();
        $otherClient = Client::factory()->for($other)->external()->create();
        $this->makeQuote($other, $otherClient);

        Sanctum::actingAs($user, ['quotes:read']);

        $this->getJson('/api/v1/quotes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', $mine->number);
    }

    public function test_show_returns_quote_with_client_and_line_items(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $quote = $this->makeQuote($user, $client);

        Sanctum::actingAs($user, ['quotes:read']);

        $this->getJson("/api/v1/quotes/{$quote->uuid}")
            ->assertOk()
            ->assertJsonPath('data.number', $quote->number)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.client.id', $client->id)
            ->assertJsonCount(1, 'data.line_items');
    }

    public function test_show_forbids_foreign_quote(): void
    {
        $other = User::factory()->create();
        $otherClient = Client::factory()->for($other)->external()->create();
        $foreign = $this->makeQuote($other, $otherClient);

        Sanctum::actingAs(User::factory()->create(), ['quotes:read']);

        $this->getJson("/api/v1/quotes/{$foreign->uuid}")->assertForbidden();
    }

    public function test_status_filter_derives_expired(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        $expired = $this->makeQuote($user, $client, [
            'status' => QuoteStatus::Sent,
            'expiry_date' => now()->subDays(3)->toDateString(),
        ]);
        $this->makeQuote($user, $client, [
            'status' => QuoteStatus::Sent,
            'expiry_date' => now()->addDays(3)->toDateString(),
        ]);

        Sanctum::actingAs($user, ['quotes:read']);

        $this->getJson('/api/v1/quotes?status=expired')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', $expired->number)
            ->assertJsonPath('data.0.status', 'expired')
            ->assertJsonPath('data.0.stored_status', 'sent');
    }

    public function test_store_creates_quote_with_quo_numbering(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        Sanctum::actingAs($user, ['quotes:write']);

        $response = $this->postJson('/api/v1/quotes', [
            'client_id' => $client->id,
            'issue_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'currency' => 'ugx',
            'line_items' => [
                ['description' => 'Work', 'quantity' => 2, 'unit_price' => 150],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.number', 'QUO-'.now()->format('Y').'-1')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.currency', 'UGX')
            ->assertJsonPath('data.amount', 300);

        $this->assertDatabaseHas('quotes', [
            'user_id' => $user->id,
            'number' => 'QUO-'.now()->format('Y').'-1',
            'amount' => '300.00',
        ]);
    }

    public function test_store_validation_errors_return_422(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['quotes:write']);

        $this->postJson('/api/v1/quotes', [
            'currency' => 'UGX',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['client_id', 'issue_date', 'line_items']);
    }

    public function test_convert_creates_draft_invoice_and_links_quote(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $quote = $this->makeQuote($user, $client, ['status' => QuoteStatus::Sent]);

        Sanctum::actingAs($user, ['quotes:write']);

        $response = $this->postJson("/api/v1/quotes/{$quote->uuid}/convert");

        $response->assertCreated()
            ->assertJsonPath('quote.status', 'accepted');

        $invoice = Invoice::query()->findOrFail($response->json('invoice.id'));
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame('300.00', $invoice->amount);
        $this->assertSame($client->id, $invoice->client_id);
        $this->assertSame($invoice->number, $response->json('invoice.number'));
        $this->assertCount(1, $invoice->lineItems);

        $quote->refresh();
        $this->assertSame(QuoteStatus::Accepted, $quote->status);
        $this->assertSame($invoice->id, $quote->converted_invoice_id);
    }

    public function test_convert_blocks_double_conversion(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $quote = $this->makeQuote($user, $client, ['status' => QuoteStatus::Sent]);

        Sanctum::actingAs($user, ['quotes:write']);

        $this->postJson("/api/v1/quotes/{$quote->uuid}/convert")->assertCreated();
        $this->postJson("/api/v1/quotes/{$quote->uuid}/convert")->assertStatus(422);

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_declined_quote_cannot_be_converted(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $quote = $this->makeQuote($user, $client, ['status' => QuoteStatus::Declined]);

        Sanctum::actingAs($user, ['quotes:write']);

        $this->postJson("/api/v1/quotes/{$quote->uuid}/convert")->assertStatus(422);

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_destroy_deletes_quote(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $quote = $this->makeQuote($user, $client);

        Sanctum::actingAs($user, ['quotes:write']);

        $this->deleteJson("/api/v1/quotes/{$quote->uuid}")->assertOk();

        $this->assertDatabaseMissing('quotes', ['id' => $quote->id]);
    }
}
