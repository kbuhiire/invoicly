<?php

namespace Tests\Feature;

use App\Enums\ClientType;
use App\Models\Client;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\User;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_sequence_continues_legacy_numbering(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $year = now()->format('Y');
        Invoice::factory()->for($user)->for($client)->create(['number' => "EINV-{$year}-7"]);

        $number = Invoice::nextNumberForUser($user, ClientType::External);

        $this->assertSame("EINV-{$year}-8", $number);
    }

    public function test_invoicly_sequence_uses_dinv_prefix(): void
    {
        $user = User::factory()->create();

        $number = Invoice::nextNumberForUser($user, ClientType::Invoicly);

        $this->assertSame('DINV-'.now()->format('Y').'-1', $number);
    }

    public function test_custom_prefix_padding_and_no_year_apply(): void
    {
        $user = User::factory()->create();
        DocumentSequence::query()->create([
            'user_id' => $user->id,
            'document_type' => DocumentNumberService::TYPE_INVOICE_EXTERNAL,
            'prefix' => 'ACME',
            'next_number' => 42,
            'padding' => 4,
            'include_year' => false,
        ]);

        $number = Invoice::nextNumberForUser($user, ClientType::External);

        $this->assertSame('ACME-0042', $number);
        $this->assertSame(43, DocumentSequence::query()->first()->next_number);
    }

    public function test_lowering_next_number_bumps_past_collisions(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        $year = now()->format('Y');
        Invoice::factory()->for($user)->for($client)->create(['number' => "EINV-{$year}-1"]);
        Invoice::factory()->for($user)->for($client)->create(['number' => "EINV-{$year}-2"]);

        DocumentSequence::query()->create([
            'user_id' => $user->id,
            'document_type' => DocumentNumberService::TYPE_INVOICE_EXTERNAL,
            'prefix' => 'EINV',
            'next_number' => 1,
            'padding' => 0,
            'include_year' => true,
        ]);

        $number = Invoice::nextNumberForUser($user, ClientType::External);

        $this->assertSame("EINV-{$year}-3", $number);
    }

    public function test_same_number_allowed_across_users(): void
    {
        $year = now()->format('Y');

        foreach (range(1, 2) as $i) {
            $user = User::factory()->create();
            $client = Client::factory()->for($user)->external()->create();
            $number = Invoice::nextNumberForUser($user, ClientType::External);
            Invoice::factory()->for($user)->for($client)->create(['number' => $number]);
            $this->assertSame("EINV-{$year}-1", $number);
        }
    }

    public function test_preview_does_not_consume_the_sequence(): void
    {
        $user = User::factory()->create();

        $first = Invoice::previewNumberForUser($user, ClientType::External);
        $second = Invoice::previewNumberForUser($user, ClientType::External);

        $this->assertSame($first, $second);
        $this->assertSame(0, DocumentSequence::query()->count());
    }

    public function test_settings_endpoint_persists_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/settings/numbering', [
            'document_type' => DocumentNumberService::TYPE_INVOICE_EXTERNAL,
            'prefix' => 'inv',
            'next_number' => 100,
            'padding' => 3,
            'include_year' => true,
        ]);

        $this->assertDatabaseHas('document_sequences', [
            'user_id' => $user->id,
            'document_type' => 'invoice_external',
            'prefix' => 'INV',
            'next_number' => 100,
            'padding' => 3,
        ]);

        $this->assertSame(
            'INV-'.now()->format('Y').'-100',
            Invoice::nextNumberForUser($user, ClientType::External)
        );
    }

    public function test_settings_endpoint_validates_prefix(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/settings/numbering', [
            'document_type' => DocumentNumberService::TYPE_INVOICE_EXTERNAL,
            'prefix' => '9bad prefix!',
            'next_number' => 1,
            'padding' => 0,
            'include_year' => true,
        ])->assertSessionHasErrors(['prefix']);
    }

    public function test_settings_page_includes_numbering_payload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/settings?tab=invoice')
            ->assertInertia(fn ($page) => $page
                ->has('numbering', 3)
                ->where('numbering.0.document_type', 'invoice_external')
                ->where('numbering.0.prefix', 'EINV'));
    }
}
