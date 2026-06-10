<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

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

    public function test_quote_can_be_created_with_quo_numbering(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        $this->actingAs($user)->post('/quotes', [
            'client_id' => $client->id,
            'issue_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'currency' => 'ugx',
            'line_items' => [
                ['description' => 'Work', 'quantity' => 2, 'unit_price' => 150],
            ],
        ])->assertRedirect(route('quotes.index', [], false));

        $quote = Quote::query()->firstOrFail();
        $this->assertSame('QUO-'.now()->format('Y').'-1', $quote->number);
        $this->assertSame(QuoteStatus::Draft, $quote->status);
        $this->assertSame('300.00', $quote->amount);
        $this->assertSame(1, $quote->lineItems()->count());
    }

    public function test_update_replaces_line_items_and_recomputes_amount(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $quote = $this->makeQuote($user, $client);

        $this->actingAs($user)->put("/quotes/{$quote->uuid}", [
            'client_id' => $client->id,
            'issue_date' => now()->toDateString(),
            'currency' => 'UGX',
            'line_items' => [
                ['description' => 'Dev', 'quantity' => 1, 'unit_price' => 1000],
            ],
        ]);

        $quote->refresh();
        $this->assertSame('1000.00', $quote->amount);
        $this->assertSame('Dev', $quote->lineItems()->first()->description);
    }

    public function test_mark_sent_stamps_sent_at(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $quote = $this->makeQuote($user, $client);

        $this->actingAs($user)->post("/quotes/{$quote->uuid}/send");

        $quote->refresh();
        $this->assertSame(QuoteStatus::Sent, $quote->status);
        $this->assertNotNull($quote->sent_at);
    }

    public function test_expired_status_is_derived_for_sent_quotes(): void
    {
        Date::setTestNow('2026-06-10 12:00:00');

        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $this->makeQuote($user, $client, [
            'status' => QuoteStatus::Sent,
            'expiry_date' => '2026-06-01',
        ]);

        $this->actingAs($user)->get('/quotes')
            ->assertInertia(fn ($page) => $page
                ->where('quotes.data.0.status', 'expired'));

        // And the expired filter finds it
        $this->actingAs($user)->get('/quotes?status=expired')
            ->assertInertia(fn ($page) => $page->has('quotes.data', 1));
    }

    public function test_convert_creates_draft_invoice_with_line_items(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $quote = $this->makeQuote($user, $client, ['status' => QuoteStatus::Sent]);

        $response = $this->actingAs($user)->post("/quotes/{$quote->uuid}/convert");

        $quote->refresh();
        $invoice = Invoice::query()->firstOrFail();

        $response->assertRedirect(route('invoices.edit', $invoice, false));
        $this->assertSame(QuoteStatus::Accepted, $quote->status);
        $this->assertSame($invoice->id, $quote->converted_invoice_id);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame('300.00', $invoice->amount);
        $this->assertSame($client->id, $invoice->client_id);
        $this->assertSame(1, $invoice->lineItems()->count());
        $this->assertStringStartsWith('EINV-', $invoice->number);
    }

    public function test_double_convert_is_blocked(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $quote = $this->makeQuote($user, $client);

        $this->actingAs($user)->post("/quotes/{$quote->uuid}/convert");
        $this->actingAs($user)
            ->from('/quotes')
            ->post("/quotes/{$quote->uuid}/convert")
            ->assertSessionHasErrors(['quote']);

        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_declined_quote_cannot_be_converted(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $quote = $this->makeQuote($user, $client, ['status' => QuoteStatus::Declined]);

        $this->actingAs($user)
            ->from('/quotes')
            ->post("/quotes/{$quote->uuid}/convert")
            ->assertSessionHasErrors(['quote']);

        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_quotes_are_tenant_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherClient = Client::factory()->for($other)->external()->create();
        $quote = $this->makeQuote($other, $otherClient);

        $this->actingAs($user)->get("/quotes/{$quote->uuid}/edit")->assertForbidden();
        $this->actingAs($user)->post("/quotes/{$quote->uuid}/convert")->assertForbidden();
        $this->actingAs($user)->get("/quotes/{$quote->uuid}/pdf")->assertForbidden();
        $this->actingAs($user)->delete("/quotes/{$quote->uuid}")->assertForbidden();

        $this->actingAs($user)->get('/quotes')
            ->assertInertia(fn ($page) => $page->has('quotes.data', 0));
    }
}
