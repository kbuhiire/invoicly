<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDuplicateTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_creates_fresh_draft_copy_with_line_items(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $invoice = Invoice::factory()->for($user)->for($client)->paid()->create([
            'number' => 'EINV-2026-5',
            'currency' => 'UGX',
            'amount' => '450.00',
            'amount_paid' => '450.00',
            'issue_date' => now()->subDays(40)->toDateString(),
            'due_date' => now()->subDays(10)->toDateString(),
            'sent_at' => now()->subDays(40),
            'attachment_path' => 'invoices/attachments/some-file.pdf',
        ]);
        $invoice->lineItems()->create(['description' => 'Design', 'quantity' => 2, 'unit_price' => 100, 'sort_order' => 0]);
        $invoice->lineItems()->create(['description' => 'Dev', 'quantity' => 1, 'unit_price' => 250, 'sort_order' => 1]);

        $response = $this->actingAs($user)->post("/invoices/{$invoice->uuid}/duplicate");

        $copy = Invoice::query()->where('user_id', $user->id)->where('id', '!=', $invoice->id)->firstOrFail();

        $response->assertRedirect(route('invoices.edit', $copy, false));

        $this->assertNotSame($invoice->uuid, $copy->uuid);
        $this->assertNotSame($invoice->number, $copy->number);
        $this->assertSame(InvoiceStatus::Draft, $copy->status);
        $this->assertSame('0.00', $copy->amount_paid);
        $this->assertNull($copy->paid_at);
        $this->assertNull($copy->sent_at);
        $this->assertNull($copy->attachment_path);
        $this->assertSame('450.00', $copy->amount);
        $this->assertTrue($copy->issue_date->isToday());
        // Original net terms (30 days) preserved relative to today.
        $this->assertSame(30, (int) $copy->issue_date->diffInDays($copy->due_date));

        $items = $copy->lineItems()->get();
        $this->assertCount(2, $items);
        $this->assertSame('Design', $items[0]->description);
        $this->assertSame('Dev', $items[1]->description);
    }

    public function test_duplicate_is_forbidden_for_other_tenants(): void
    {
        $user = User::factory()->create();
        $other = Invoice::factory()->create();

        $this->actingAs($user)->post("/invoices/{$other->uuid}/duplicate")->assertForbidden();
        $this->assertSame(1, Invoice::query()->count());
    }
}
