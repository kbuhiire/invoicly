<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/clients')->assertRedirect('/login');
    }

    public function test_index_lists_only_own_clients_for_segment(): void
    {
        $user = User::factory()->create();
        $mine = Client::factory()->for($user)->external()->create(['name' => 'Acme Ltd']);
        Client::factory()->for($user)->invoicly()->create(['name' => 'Internal Co']);
        Client::factory()->external()->create(['name' => 'Other Tenant']);

        $response = $this->actingAs($user)->get('/clients?segment=external');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Clients/Index')
                ->where('segment', 'external')
                ->has('clients.data', 1)
                ->where('clients.data.0.uuid', $mine->uuid));
    }

    public function test_index_search_filters_by_name_and_email(): void
    {
        $user = User::factory()->create();
        Client::factory()->for($user)->external()->create(['name' => 'Acme Ltd', 'email' => null]);
        Client::factory()->for($user)->external()->create(['name' => 'Zeta Inc', 'email' => 'billing@zeta.test']);

        $this->actingAs($user)->get('/clients?segment=external&search=zeta')
            ->assertInertia(fn ($page) => $page
                ->has('clients.data', 1)
                ->where('clients.data.0.name', 'Zeta Inc'));

        $this->actingAs($user)->get('/clients?segment=external&search=billing@zeta')
            ->assertInertia(fn ($page) => $page->has('clients.data', 1));
    }

    public function test_show_displays_client_with_invoices_and_outstanding(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        Invoice::factory()->for($user)->for($client)->awaitingPayment()->create([
            'amount' => '500.00',
            'amount_paid' => '0',
        ]);

        $this->actingAs($user)->get("/clients/{$client->uuid}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Clients/Show')
                ->where('client.uuid', $client->uuid)
                ->has('invoices', 1)
                ->where('invoices.0.outstanding', '500.00'));
    }

    public function test_show_is_forbidden_for_other_tenants(): void
    {
        $user = User::factory()->create();
        $other = Client::factory()->external()->create();

        $this->actingAs($user)->get("/clients/{$other->uuid}")->assertForbidden();
    }

    public function test_store_creates_business_client_with_derived_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/clients', [
            'type' => 'external',
            'is_business' => true,
            'business_name' => 'Globex Corporation',
            'vat_number' => 'VAT-001',
            'country' => 'UG',
            'street' => '1 Main St',
            'city' => 'Kampala',
            'postal_code' => '0000',
            'email' => 'ap@globex.test',
        ]);

        $response->assertRedirect(route('clients.index', ['segment' => 'external'], false));
        $this->assertDatabaseHas('clients', [
            'user_id' => $user->id,
            'name' => 'Globex Corporation',
            'business_name' => 'Globex Corporation',
            'is_business' => true,
        ]);
    }

    public function test_store_creates_person_client_with_derived_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/clients', [
            'type' => 'invoicly',
            'is_business' => false,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'country' => 'UG',
            'street' => '1 Main St',
            'city' => 'Kampala',
            'postal_code' => '0000',
        ]);

        $this->assertDatabaseHas('clients', [
            'user_id' => $user->id,
            'name' => 'Jane Doe',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'type' => 'invoicly',
        ]);
    }

    public function test_store_validates_business_requires_business_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/clients', [
            'type' => 'external',
            'is_business' => true,
            'country' => 'UG',
            'street' => '1 Main St',
            'city' => 'Kampala',
            'postal_code' => '0000',
        ])->assertSessionHasErrors(['business_name']);
    }

    public function test_update_via_form_request_changes_client(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        $this->actingAs($user)->patch("/clients/{$client->uuid}", [
            'is_business' => false,
            'first_name' => 'John',
            'last_name' => 'Smith',
            'country' => 'UG',
            'street' => '2 Side St',
            'city' => 'Entebbe',
            'postal_code' => '1111',
            'phone' => '+256700000000',
        ]);

        $client->refresh();
        $this->assertSame('John Smith', $client->name);
        $this->assertSame('+256700000000', $client->phone);
        $this->assertFalse($client->is_business);
    }

    public function test_update_is_forbidden_for_other_tenants(): void
    {
        $user = User::factory()->create();
        $other = Client::factory()->external()->create();

        $this->actingAs($user)->patch("/clients/{$other->uuid}", [
            'is_business' => true,
            'business_name' => 'Hijack Co',
            'country' => 'UG',
            'street' => 'x',
            'city' => 'x',
            'postal_code' => 'x',
        ])->assertForbidden();
    }

    public function test_destroy_blocked_when_client_has_invoices(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();
        Invoice::factory()->for($user)->for($client)->create();

        $this->actingAs($user)
            ->from('/clients')
            ->delete("/clients/{$client->uuid}")
            ->assertSessionHasErrors(['client']);

        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    public function test_destroy_deletes_client_without_invoices(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->external()->create();

        $this->actingAs($user)->from('/clients')->delete("/clients/{$client->uuid}");

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }
}
