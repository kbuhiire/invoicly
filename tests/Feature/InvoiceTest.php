<?php

namespace Tests\Feature;

use App\Enums\ClientType;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_invoices_index(): void
    {
        $this->get(route('invoices.index', ['segment' => 'external']))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_invoices_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('invoices.index', ['segment' => 'external']))
            ->assertOk();
    }

    public function test_user_can_create_invoice_with_line_items(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        $payload = [
            'client_id' => $client->id,
            'issue_date' => '2026-03-01',
            'status' => InvoiceStatus::AwaitingPayment->value,
            'currency' => 'UGX',
            'amount_secondary' => '120.50',
            'currency_secondary' => 'EUR',
            'line_items' => [
                ['description' => 'Services', 'quantity' => '2', 'unit_price' => '100.00'],
                ['description' => 'Fee', 'quantity' => '1', 'unit_price' => '50.00'],
            ],
        ];

        $this->actingAs($user)
            ->post(route('invoices.store'), $payload)
            ->assertRedirect();

        $invoice = Invoice::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('250.00', $invoice->amount);
        $this->assertCount(2, $invoice->lineItems);
        $this->assertStringStartsWith('EINV-2026-', $invoice->number);
    }

    public function test_user_can_create_invoice_with_new_business_client(): void
    {
        $user = User::factory()->create();

        $payload = [
            'new_client_is_business' => true,
            'new_client_business_name' => 'TAAGSOLUTIONS GmbH',
            'new_client_country' => 'DE',
            'new_client_street' => 'Musterstraße 1',
            'new_client_city' => 'Berlin',
            'new_client_postal_code' => '10115',
            'new_client_vat_number' => 'DE123456789',
            'new_client_email' => 'billing@example.com',
            'segment' => 'external',
            'issue_date' => '2026-03-15',
            'status' => InvoiceStatus::AwaitingPayment->value,
            'currency' => 'EUR',
            'line_items' => [
                ['description' => 'Consulting', 'quantity' => '1', 'unit_price' => '500.00'],
            ],
        ];

        $this->actingAs($user)
            ->post(route('invoices.store'), $payload)
            ->assertRedirect(route('invoices.index', ['segment' => 'external']));

        $client = Client::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($client);
        $this->assertTrue($client->is_business);
        $this->assertSame('TAAGSOLUTIONS GmbH', $client->name);
        $this->assertSame('TAAGSOLUTIONS GmbH', $client->business_name);
        $this->assertSame('DE', $client->country);
        $this->assertSame('DE123456789', $client->vat_number);
        $this->assertSame(ClientType::External, $client->type);

        $invoice = Invoice::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame($client->id, $invoice->client_id);
        $this->assertSame('500.00', $invoice->amount);
    }

    public function test_user_can_create_invoice_with_new_individual_client(): void
    {
        $user = User::factory()->create();

        $payload = [
            'new_client_is_business' => false,
            'new_client_first_name' => 'Jane',
            'new_client_last_name' => 'Contractor',
            'new_client_country' => 'UG',
            'new_client_street' => 'Kamuli RD',
            'new_client_city' => 'Kampala',
            'new_client_postal_code' => '256',
            'new_client_email' => 'jane@example.com',
            'segment' => 'external',
            'issue_date' => '2026-03-15',
            'status' => InvoiceStatus::AwaitingPayment->value,
            'currency' => 'UGX',
            'line_items' => [
                ['description' => 'Services', 'quantity' => '1', 'unit_price' => '100.00'],
            ],
        ];

        $this->actingAs($user)
            ->post(route('invoices.store'), $payload)
            ->assertRedirect(route('invoices.index', ['segment' => 'external']));

        $client = Client::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($client);
        $this->assertFalse($client->is_business);
        $this->assertSame('Jane Contractor', $client->name);
        $this->assertSame('Jane', $client->first_name);
        $this->assertSame('Contractor', $client->last_name);
        $this->assertNull($client->business_name);
        $this->assertSame('UG', $client->country);
        $this->assertNull($client->vat_number);
    }

    public function test_user_cannot_download_pdf_for_foreign_invoice(): void
    {
        Pdf::fake();

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $client = Client::factory()->for($owner)->external()->create();
        $invoice = Invoice::factory()->for($owner)->for($client)->create();

        $this->actingAs($other)
            ->get(route('invoices.pdf', $invoice))
            ->assertForbidden();
    }

    public function test_user_can_download_own_invoice_pdf(): void
    {
        Pdf::fake();

        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $invoice = Invoice::factory()->for($user)->for($client)->create();

        $this->actingAs($user)
            ->get(route('invoices.pdf', $invoice))
            ->assertOk();

        Pdf::assertRespondedWithPdf(fn () => true);
    }

    public function test_user_can_download_attachment_when_present(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $invoice = Invoice::factory()->for($user)->for($client)->create([
            'attachment_path' => 'invoices/attachments/test.txt',
        ]);

        Storage::disk('local')->put('invoices/attachments/test.txt', 'hello');

        $this->actingAs($user)
            ->get(route('invoices.attachment', $invoice))
            ->assertOk();
    }

    public function test_foreign_user_cannot_download_attachment(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $client = Client::factory()->for($owner)->external()->create();
        $invoice = Invoice::factory()->for($owner)->for($client)->create([
            'attachment_path' => 'invoices/attachments/secret.txt',
        ]);

        Storage::disk('local')->put('invoices/attachments/secret.txt', 'secret');

        $this->actingAs($other)
            ->get(route('invoices.attachment', $invoice))
            ->assertForbidden();
    }

    public function test_user_can_update_preferred_currency(): void
    {
        $user = User::factory()->create(['preferred_currency' => 'UGX']);

        $this->actingAs($user)
            ->patch(route('user.preferred-currency'), [
                'preferred_currency' => 'EUR',
            ])
            ->assertRedirect();

        $this->assertSame('EUR', $user->fresh()->preferred_currency);
    }

    public function test_store_accepts_file_attachment(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $file = UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf');

        $payload = [
            'client_id' => $client->id,
            'issue_date' => '2026-03-01',
            'status' => InvoiceStatus::Paid->value,
            'currency' => 'UGX',
            'line_items' => [
                ['description' => 'Item', 'quantity' => '1', 'unit_price' => '10.00'],
            ],
            'attachment' => $file,
        ];

        $this->actingAs($user)
            ->post(route('invoices.store'), $payload)
            ->assertRedirect();

        $invoice = Invoice::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($invoice->attachment_path);
        Storage::disk('local')->assertExists($invoice->attachment_path);
    }
}
