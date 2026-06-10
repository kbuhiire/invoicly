<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_export(): void
    {
        $this->get('/invoices/export')->assertRedirect('/login');
        $this->get('/payments/export')->assertRedirect('/login');
    }

    public function test_invoice_export_streams_csv_with_own_rows_only(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create(['name' => 'Acme Ltd']);
        Invoice::factory()->for($user)->for($client)->awaitingPayment()->create([
            'number' => 'EINV-2026-77',
            'amount' => '500.00',
            'amount_paid' => '0',
        ]);
        Invoice::factory()->create(['number' => 'EINV-2026-99']); // other tenant

        $response = $this->actingAs($user)->get('/invoices/export?segment=external');

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('EINV-2026-77', $csv);
        $this->assertStringContainsString('Acme Ltd', $csv);
        $this->assertStringNotContainsString('EINV-2026-99', $csv);
    }

    public function test_invoice_export_respects_status_and_search_filters(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        Invoice::factory()->for($user)->for($client)->paid()->create(['number' => 'EINV-2026-1']);
        Invoice::factory()->for($user)->for($client)->awaitingPayment()->create(['number' => 'EINV-2026-2']);

        $csv = $this->actingAs($user)
            ->get('/invoices/export?segment=external&status=paid')
            ->streamedContent();

        $this->assertStringContainsString('EINV-2026-1', $csv);
        $this->assertStringNotContainsString('EINV-2026-2', $csv);

        $csv = $this->actingAs($user)
            ->get('/invoices/export?segment=external&search=EINV-2026-2')
            ->streamedContent();

        $this->assertStringNotContainsString('EINV-2026-1,', $csv);
        $this->assertStringContainsString('EINV-2026-2', $csv);
    }

    public function test_invoice_export_marks_overdue_rows(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        Invoice::factory()->for($user)->for($client)->awaitingPayment()->create([
            'number' => 'EINV-2026-10',
            'due_date' => now()->subDays(5)->toDateString(),
            'amount_paid' => '0',
        ]);

        $csv = $this->actingAs($user)->get('/invoices/export?segment=external')->streamedContent();

        $row = collect(explode("\n", $csv))->first(fn ($line) => str_contains($line, 'EINV-2026-10'));
        $this->assertNotNull($row);
        $this->assertStringContainsString(',yes,', $row);
    }

    public function test_payments_export_streams_user_payments(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $invoice = Invoice::factory()->for($user)->for($client)->awaitingPayment()->create([
            'number' => 'EINV-2026-55',
        ]);
        Payment::factory()->for($user)->for($invoice)->create([
            'amount' => '120.00',
            'reference' => 'bank-ref-1',
        ]);
        Payment::factory()->create(['reference' => 'foreign-ref']); // other tenant

        $csv = $this->actingAs($user)->get('/payments/export')->streamedContent();

        $this->assertStringContainsString('bank-ref-1', $csv);
        $this->assertStringContainsString('EINV-2026-55', $csv);
        $this->assertStringNotContainsString('foreign-ref', $csv);
    }
}
