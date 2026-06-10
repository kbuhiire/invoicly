<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxRateTest extends TestCase
{
    use RefreshDatabase;

    private function rate(User $user, array $attributes = []): TaxRate
    {
        return $user->taxRates()->create(array_merge([
            'name' => 'VAT 18%',
            'rate' => '18.000',
            'is_default' => false,
        ], $attributes));
    }

    public function test_tax_rate_can_be_created(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/settings/tax-rates', [
            'name' => 'VAT 18%',
            'rate' => 18,
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('tax_rates', [
            'user_id' => $user->id,
            'name' => 'VAT 18%',
            'is_default' => true,
        ]);
    }

    public function test_setting_default_clears_previous_default(): void
    {
        $user = User::factory()->create();
        $old = $this->rate($user, ['name' => 'Old default', 'is_default' => true]);

        $this->actingAs($user)->post('/settings/tax-rates', [
            'name' => 'New default',
            'rate' => 16,
            'is_default' => true,
        ]);

        $this->assertFalse($old->refresh()->is_default);
        $this->assertSame(1, TaxRate::query()->where('user_id', $user->id)->where('is_default', true)->count());
    }

    public function test_update_and_delete_are_tenant_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreign = $this->rate($other);

        $this->actingAs($user)->patch("/settings/tax-rates/{$foreign->uuid}", [
            'name' => 'Hijack',
            'rate' => 1,
        ])->assertForbidden();

        $this->actingAs($user)->delete("/settings/tax-rates/{$foreign->uuid}")->assertForbidden();
        $this->assertDatabaseHas('tax_rates', ['id' => $foreign->id]);
    }

    public function test_rate_validation_rejects_out_of_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/settings/tax-rates', [
            'name' => 'Bad',
            'rate' => 101,
        ])->assertSessionHasErrors(['rate']);
    }

    public function test_invoice_store_persists_tax_rate_and_vat_amount(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $rate = $this->rate($user);

        $this->actingAs($user)->post('/invoices', [
            'client_id' => $client->id,
            'issue_date' => now()->toDateString(),
            'status' => 'awaiting_payment',
            'currency' => 'UGX',
            'vat_amount' => '54.00',
            'tax_rate_id' => $rate->id,
            'line_items' => [
                ['description' => 'Work', 'quantity' => 1, 'unit_price' => 300],
            ],
        ]);

        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'tax_rate_id' => $rate->id,
            'vat_amount' => '54.00',
        ]);
    }

    public function test_invoice_store_rejects_foreign_tax_rate(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $foreign = $this->rate(User::factory()->create());

        $this->actingAs($user)->post('/invoices', [
            'client_id' => $client->id,
            'issue_date' => now()->toDateString(),
            'status' => 'awaiting_payment',
            'currency' => 'UGX',
            'tax_rate_id' => $foreign->id,
            'line_items' => [
                ['description' => 'Work', 'quantity' => 1, 'unit_price' => 300],
            ],
        ])->assertSessionHasErrors(['tax_rate_id']);
    }

    public function test_deleting_rate_nulls_invoice_reference(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $rate = $this->rate($user);
        $invoice = Invoice::factory()->for($user)->for($client)->create([
            'tax_rate_id' => $rate->id,
            'vat_amount' => '54.00',
        ]);

        $this->actingAs($user)->delete("/settings/tax-rates/{$rate->uuid}");

        $invoice->refresh();
        $this->assertNull($invoice->tax_rate_id);
        $this->assertSame('54.00', $invoice->vat_amount);
    }

    public function test_settings_page_lists_tax_rates(): void
    {
        $user = User::factory()->create();
        $this->rate($user, ['is_default' => true]);

        $this->actingAs($user)->get('/settings?tab=bookkeeping')
            ->assertInertia(fn ($page) => $page
                ->has('taxRates', 1)
                ->where('taxRates.0.name', 'VAT 18%'));
    }
}
