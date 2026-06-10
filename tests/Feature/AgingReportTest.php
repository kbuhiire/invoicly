<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class AgingReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    private function open(User $user, Client $client, ?string $dueDate, array $attributes = []): Invoice
    {
        return Invoice::factory()->for($user)->for($client)->awaitingPayment()->create(array_merge([
            'currency' => 'UGX',
            'amount' => '100.00',
            'amount_paid' => '0',
            'due_date' => $dueDate,
        ], $attributes));
    }

    public function test_buckets_have_correct_boundaries(): void
    {
        Date::setTestNow('2026-06-10 12:00:00');

        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        $this->open($user, $client, null);                                  // current (no due date)
        $this->open($user, $client, '2026-06-10');                          // current (due today)
        $this->open($user, $client, '2026-05-11', ['amount' => '200.00']);  // 30 days -> 1-30
        $this->open($user, $client, '2026-05-10', ['amount' => '300.00']);  // 31 days -> 31-60
        $this->open($user, $client, '2026-04-11', ['amount' => '400.00']);  // 60 days -> 31-60
        $this->open($user, $client, '2026-03-12', ['amount' => '500.00']);  // 90 days -> 61-90
        $this->open($user, $client, '2026-03-11', ['amount' => '600.00']);  // 91 days -> 90+

        $this->actingAs($user)->get('/reports/aging')
            ->assertInertia(fn ($page) => $page
                ->component('Reports/Aging')
                ->has('rows', 1)
                ->where('rows.0.buckets.current', '200.00')
                ->where('rows.0.buckets.b1_30', '200.00')
                ->where('rows.0.buckets.b31_60', '700.00')
                ->where('rows.0.buckets.b61_90', '500.00')
                ->where('rows.0.buckets.b90_plus', '600.00')
                ->where('rows.0.total', '2200.00'));
    }

    public function test_partial_payments_use_outstanding_not_amount(): void
    {
        Date::setTestNow('2026-06-10 12:00:00');

        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $this->open($user, $client, '2026-06-01', [
            'amount' => '500.00',
            'amount_paid' => '350.00',
            'status' => 'partially_paid',
        ]);

        $this->actingAs($user)->get('/reports/aging')
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.buckets.b1_30', '150.00')
                ->where('rows.0.total', '150.00'));
    }

    public function test_drafts_templates_and_paid_are_excluded(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        Invoice::factory()->for($user)->for($client)->draft()->create(['amount' => '100.00']);
        Invoice::factory()->for($user)->for($client)->paid()->create(['amount' => '100.00', 'amount_paid' => '100.00']);
        Invoice::factory()->for($user)->for($client)->awaitingPayment()->create([
            'amount' => '100.00',
            'amount_paid' => '0',
            'is_template' => true,
        ]);

        $this->actingAs($user)->get('/reports/aging')
            ->assertInertia(fn ($page) => $page->has('rows', 0));
    }

    public function test_currencies_are_never_summed_together(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $this->open($user, $client, null, ['currency' => 'UGX', 'amount' => '100.00']);
        $this->open($user, $client, null, ['currency' => 'USD', 'amount' => '50.00']);

        $this->actingAs($user)->get('/reports/aging')
            ->assertInertia(fn ($page) => $page
                ->has('rows', 2)
                ->has('totals', 2));
    }

    public function test_report_is_tenant_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherClient = Client::factory()->for($other)->external()->create();
        $this->open($other, $otherClient, null);

        $this->actingAs($user)->get('/reports/aging')
            ->assertInertia(fn ($page) => $page->has('rows', 0));
    }

    public function test_csv_export_contains_rows(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create(['name' => 'Acme Ltd']);
        $this->open($user, $client, null);

        $csv = $this->actingAs($user)->get('/reports/aging/export')->streamedContent();

        $this->assertStringContainsString('Acme Ltd', $csv);
        $this->assertStringContainsString('100.00', $csv);
    }

    public function test_invoice_index_filters_by_client_id(): void
    {
        $user = User::factory()->create();
        $clientA = Client::factory()->for($user)->external()->create();
        $clientB = Client::factory()->for($user)->external()->create();
        $invoiceA = $this->open($user, $clientA, null);
        $this->open($user, $clientB, null);

        $this->actingAs($user)->get("/invoices?segment=external&client_id={$clientA->id}")
            ->assertInertia(fn ($page) => $page
                ->has('invoices.data', 1)
                ->where('invoices.data.0.uuid', $invoiceA->uuid)
                ->where('filters.client_id', $clientA->id));
    }
}
