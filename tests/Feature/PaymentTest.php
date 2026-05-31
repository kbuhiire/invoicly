<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function invoiceFor(User $user, string $amount = '250.00'): Invoice
    {
        $client = Client::factory()->for($user)->external()->create();

        return Invoice::factory()->for($user)->for($client)->awaitingPayment()->create([
            'amount' => $amount,
            'amount_paid' => '0',
        ]);
    }

    public function test_full_payment_marks_invoice_paid(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user, '250.00');

        $this->actingAs($user)
            ->post(route('invoices.payments.store', $invoice->uuid), [
                'amount' => '250.00',
                'paid_at' => '2026-05-01',
                'reference' => 'BANK-001',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('250.00', $invoice->amount_paid);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame('0.00', $invoice->outstandingAmount());
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_partial_payment_marks_invoice_partially_paid(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user, '250.00');

        $this->actingAs($user)
            ->post(route('invoices.payments.store', $invoice->uuid), [
                'amount' => '100.00',
                'paid_at' => '2026-05-01',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
        $this->assertSame('100.00', $invoice->amount_paid);
        $this->assertNull($invoice->paid_at);
        $this->assertSame('150.00', $invoice->outstandingAmount());
    }

    public function test_multiple_payments_accumulate_to_paid(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user, '250.00');

        foreach (['100.00', '150.00'] as $amount) {
            $this->actingAs($user)->post(route('invoices.payments.store', $invoice->uuid), [
                'amount' => $amount,
                'paid_at' => '2026-05-01',
            ])->assertRedirect();
        }

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('250.00', $invoice->amount_paid);
    }

    public function test_deleting_a_payment_recomputes_invoice_status(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user, '250.00');

        $this->actingAs($user)->post(route('invoices.payments.store', $invoice->uuid), [
            'amount' => '250.00',
            'paid_at' => '2026-05-01',
        ])->assertRedirect();

        $payment = Payment::query()->where('invoice_id', $invoice->id)->firstOrFail();

        $this->actingAs($user)
            ->delete(route('payments.destroy', $payment->uuid))
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::AwaitingPayment, $invoice->status);
        $this->assertSame('0.00', $invoice->amount_paid);
        $this->assertNull($invoice->paid_at);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_user_cannot_record_payment_on_another_users_invoice(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->invoiceFor($owner, '250.00');

        $this->actingAs(User::factory()->create())
            ->post(route('invoices.payments.store', $invoice->uuid), [
                'amount' => '50.00',
                'paid_at' => '2026-05-01',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_amount_is_required_and_positive(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user, '250.00');

        $this->actingAs($user)
            ->from(route('invoices.index', ['segment' => 'external']))
            ->post(route('invoices.payments.store', $invoice->uuid), [
                'amount' => '0',
                'paid_at' => '2026-05-01',
            ])
            ->assertSessionHasErrors('amount');
    }
}
