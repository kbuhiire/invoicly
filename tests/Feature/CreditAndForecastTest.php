<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\ClientBehaviorService;
use App\Services\ForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreditAndForecastTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a fully-paid invoice with explicit issue/due/paid dates so behaviour
     * aggregates are deterministic.
     */
    private function paidInvoice(User $user, Client $client, string $issue, string $due, string $paid, string $amount = '100.00'): Invoice
    {
        $invoice = Invoice::factory()->for($user)->for($client)->paid()->create([
            'currency' => 'UGX',
            'amount' => $amount,
            'amount_paid' => $amount,
            'issue_date' => $issue,
            'due_date' => $due,
            'paid_at' => $paid,
        ]);

        Payment::factory()->for($user)->for($invoice)->create([
            'amount' => $amount,
            'currency' => 'UGX',
            'paid_at' => $paid,
        ]);

        return $invoice;
    }

    public function test_behavior_aggregates_are_computed(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        // Paid 5 and 15 days after issue; both on time (due 30 days out).
        $this->paidInvoice($user, $client, '2026-01-01', '2026-01-31', '2026-01-06'); // 5 days
        $this->paidInvoice($user, $client, '2026-02-01', '2026-03-03', '2026-02-16'); // 15 days

        app(ClientBehaviorService::class)->recalculate($client);
        $client->refresh();

        $this->assertSame(2, $client->paid_invoice_count);
        $this->assertSame(10, $client->avg_days_to_pay); // median of 5,15
        $this->assertSame(100, $client->on_time_rate);
        $this->assertSame(0, $client->late_count);
        $this->assertSame('low', $client->credit_risk_level);
        $this->assertNotNull($client->credit_score);
        $this->assertFalse($client->flagged_for_review);
    }

    public function test_late_payer_is_flagged_high_risk(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        // Three invoices each paid ~60 days after a 7-day due date.
        $this->paidInvoice($user, $client, '2026-01-01', '2026-01-08', '2026-03-02');
        $this->paidInvoice($user, $client, '2026-02-01', '2026-02-08', '2026-04-02');
        $this->paidInvoice($user, $client, '2026-03-01', '2026-03-08', '2026-05-01');

        app(ClientBehaviorService::class)->recalculate($client);
        $client->refresh();

        $this->assertSame(3, $client->late_count);
        $this->assertSame(0, $client->on_time_rate);
        $this->assertSame('high', $client->credit_risk_level);
        $this->assertTrue($client->flagged_for_review);
    }

    public function test_insufficient_history_yields_no_score(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        // Only one paid invoice (min_history default is 2).
        $this->paidInvoice($user, $client, '2026-01-01', '2026-01-31', '2026-01-10');

        app(ClientBehaviorService::class)->recalculate($client);
        $client->refresh();

        $this->assertSame(1, $client->paid_invoice_count);
        $this->assertNull($client->credit_score);
        $this->assertNull($client->credit_risk_level);
    }

    public function test_payment_received_recomputes_client_behavior(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        $invoice = Invoice::factory()->for($user)->for($client)->awaitingPayment()->create([
            'currency' => 'UGX',
            'amount' => '200.00',
            'amount_paid' => '0',
            'issue_date' => '2026-01-01',
        ]);

        // Recording a full payment fires PaymentReceived -> listener recomputes.
        $this->actingAs($user)->post(route('invoices.payments.store', $invoice->uuid), [
            'amount' => '200.00',
            'paid_at' => '2026-01-08',
        ])->assertRedirect();

        $client->refresh();
        $this->assertSame(1, $client->paid_invoice_count);
        $this->assertNotNull($client->behavior_recomputed_at);
    }

    public function test_forecast_buckets_open_invoices_by_predicted_date(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        // Give the client a known avg_days_to_pay of ~10 via two paid invoices.
        $this->paidInvoice($user, $client, '2026-01-01', '2026-01-31', '2026-01-11'); // 10
        $this->paidInvoice($user, $client, '2026-02-01', '2026-03-03', '2026-02-11'); // 10
        app(ClientBehaviorService::class)->recalculate($client);

        // Open invoice issued today -> predicted ~10 days out, within horizon.
        Invoice::factory()->for($user)->for($client)->awaitingPayment()->create([
            'currency' => 'UGX',
            'amount' => '500.00',
            'amount_paid' => '0',
            'issue_date' => now()->toDateString(),
        ]);

        $forecast = app(ForecastService::class)->forUser($user);

        $this->assertSame('UGX', $forecast['currency']);
        $this->assertSame(500.0, $forecast['total_expected']);
        $this->assertSame(0.0, $forecast['overdue_expected']);
    }

    public function test_overdue_open_invoice_is_reported_as_overdue_inflow(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        // No history -> default 30 days; issued 90 days ago -> predicted in the past.
        Invoice::factory()->for($user)->for($client)->awaitingPayment()->create([
            'currency' => 'UGX',
            'amount' => '300.00',
            'amount_paid' => '0',
            'issue_date' => now()->subDays(90)->toDateString(),
        ]);

        $forecast = app(ForecastService::class)->forUser($user);

        $this->assertSame(300.0, $forecast['overdue_expected']);
        $this->assertSame(1, $forecast['overdue_count']);
    }

    public function test_credit_score_api_requires_ability(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        Sanctum::actingAs($user, ['invoices:read']);
        $this->getJson("/api/v1/clients/{$client->uuid}/credit-score")->assertForbidden();

        Sanctum::actingAs($user, ['clients:score']);
        $this->getJson("/api/v1/clients/{$client->uuid}/credit-score")
            ->assertOk()
            ->assertJsonPath('data.client_id', $client->id);
    }

    public function test_forecast_api_returns_buckets(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['forecasts:read']);
        $this->getJson('/api/v1/forecast')
            ->assertOk()
            ->assertJsonStructure(['data' => ['currency', 'horizon_weeks', 'total_expected', 'buckets']]);
    }

    public function test_recalculate_command_runs(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $this->paidInvoice($user, $client, '2026-01-01', '2026-01-31', '2026-01-06');
        $this->paidInvoice($user, $client, '2026-02-01', '2026-03-03', '2026-02-16');

        $this->artisan('clients:recalculate-behavior')->assertSuccessful();

        $this->assertSame(2, $client->refresh()->paid_invoice_count);
    }
}
