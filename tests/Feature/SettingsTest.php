<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_index_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('settings.index', ['tab' => 'personal']));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Settings/Index')
                ->has('activeTab')
                ->has('countries')
        );
    }

    public function test_personal_settings_can_be_updated(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/settings/personal', [
                'email' => 'new@example.com',
                'legal_first_name' => 'Test',
                'legal_last_name' => 'User',
                'phone' => '+1000000000',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.index', ['tab' => 'personal']));

        $user->refresh();
        $this->assertSame('Test User', $user->name);
        $this->assertSame('new@example.com', $user->email);
        $this->assertSame('+1000000000', $user->phone);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_email_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/personal', [
                'email' => $user->email,
                'legal_first_name' => 'Jane',
                'legal_last_name' => 'Doe',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.index', ['tab' => 'personal']));

        $this->assertNotNull($user->refresh()->email_verified_at);
        $this->assertSame('Jane Doe', $user->name);
    }

    public function test_personal_settings_can_update_phone_and_profile_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $photo = UploadedFile::fake()->image('photo.png', 120, 120);

        $response = $this
            ->actingAs($user)
            ->patch('/settings/personal', [
                'email' => $user->email,
                'legal_first_name' => 'Ken',
                'legal_last_name' => 'Example',
                'phone' => '+256787075772',
                'photo' => $photo,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.index', ['tab' => 'personal']));

        $user->refresh();
        $this->assertSame('+256787075772', $user->phone);
        $this->assertNotNull($user->logo_path);
        Storage::disk('public')->assertExists($user->logo_path);
    }

    public function test_address_settings_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/address', [
                'personal_address' => [
                    'line1' => 'Bakisula Apartments',
                    'line2' => 'Kamuli RD Kireka',
                    'city' => 'KAMPALA',
                    'region' => 'Wakiso',
                    'postal_code' => '256',
                    'country_code' => 'UG',
                ],
                'postal_address' => [
                    'line1' => '',
                    'line2' => '',
                    'city' => '',
                    'region' => '',
                    'postal_code' => '',
                    'country_code' => '',
                ],
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.index', ['tab' => 'personal']));

        $user->refresh();
        $this->assertIsArray($user->personal_address);
        $this->assertSame('Bakisula Apartments', $user->personal_address['line1']);
        $this->assertSame('UG', $user->personal_address['country_code']);
        $this->assertStringContainsString('Bakisula Apartments', (string) $user->address);
        $this->assertSame('Uganda', $user->country);
    }

    public function test_personal_edit_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/settings/personal/edit');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Settings/PersonalEdit')
                ->has('countries')
                ->has('timezones')
        );
    }

    public function test_address_edit_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/settings/address/edit');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Settings/AddressEdit')
                ->has('countries')
        );
    }

    public function test_invoice_settings_can_be_updated(): void
    {
        $user = User::factory()->create([
            'preferred_currency' => 'UGX',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/settings/invoice', [
                'invoice_show_email' => true,
                'invoice_show_phone' => false,
                'invoice_note' => 'Payment due within 14 days.',
                'invoice_type' => 'product',
                'preferred_currency' => 'EUR',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.index', ['tab' => 'invoice']));

        $user->refresh();
        $this->assertTrue($user->invoice_show_email);
        $this->assertFalse($user->invoice_show_phone);
        $this->assertSame('Payment due within 14 days.', $user->invoice_note);
        $this->assertSame('product', $user->invoice_type);
        $this->assertSame('EUR', $user->preferred_currency);
    }

    public function test_invoice_signature_can_be_uploaded(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $sig = UploadedFile::fake()->image('sig.png', 200, 80);

        $response = $this
            ->actingAs($user)
            ->patch('/settings/invoice', [
                'invoice_show_email' => false,
                'invoice_show_phone' => true,
                'invoice_note' => null,
                'invoice_type' => 'service',
                'preferred_currency' => 'UGX',
                'signature' => $sig,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.index', ['tab' => 'invoice']));

        $user->refresh();
        $this->assertNotNull($user->invoice_signature_path);
        Storage::disk('public')->assertExists($user->invoice_signature_path);
    }

    public function test_invoice_preview_pdf_is_returned(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('settings.invoice.preview'));

        $response->assertOk();
        $this->assertStringContainsString('pdf', (string) $response->headers->get('content-type'));
    }

    public function test_invoice_address_can_be_set_to_personal(): void
    {
        $user = User::factory()->create([
            'invoice_use_personal_address' => false,
            'invoice_address' => [
                'line1' => 'Old Street',
                'city' => 'Old City',
                'postal_code' => '000',
                'country_code' => 'UG',
            ],
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('settings.invoice.address.update'), [
                'invoice_use_personal_address' => true,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.index', ['tab' => 'invoice']));

        $user->refresh();
        $this->assertTrue($user->invoice_use_personal_address);
        $this->assertNull($user->invoice_address);
    }

    public function test_invoice_address_can_be_customized(): void
    {
        $user = User::factory()->create([
            'invoice_use_personal_address' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('settings.invoice.address.update'), [
                'invoice_use_personal_address' => false,
                'invoice_address' => [
                    'line1' => 'Bakisula Apartments',
                    'line2' => 'Kamuli RD Kireka',
                    'city' => 'Kampala',
                    'region' => 'Wakiso',
                    'postal_code' => '256',
                    'country_code' => 'UG',
                ],
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.index', ['tab' => 'invoice']));

        $user->refresh();
        $this->assertFalse($user->invoice_use_personal_address);
        $this->assertIsArray($user->invoice_address);
        $this->assertSame('Bakisula Apartments', $user->invoice_address['line1']);
        $this->assertSame('Kampala', $user->invoice_address['city']);
        $this->assertSame('256', $user->invoice_address['postal_code']);
        $this->assertSame('UG', $user->invoice_address['country_code']);
    }

    public function test_invoice_phone_can_be_set_to_personal(): void
    {
        $user = User::factory()->create([
            'invoice_use_personal_phone' => false,
            'invoice_phone_dial_code' => '+256',
            'invoice_phone_national' => '787075772',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('settings.invoice.phone.update'), [
                'invoice_use_personal_phone' => true,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.index', ['tab' => 'invoice']));

        $user->refresh();
        $this->assertTrue($user->invoice_use_personal_phone);
        $this->assertNull($user->invoice_phone_dial_code);
        $this->assertNull($user->invoice_phone_national);
    }

    public function test_invoice_phone_can_be_customized(): void
    {
        $user = User::factory()->create([
            'invoice_use_personal_phone' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('settings.invoice.phone.update'), [
                'invoice_use_personal_phone' => false,
                'invoice_phone_dial_code' => '+256',
                'invoice_phone_national' => '787075772',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.index', ['tab' => 'invoice']));

        $user->refresh();
        $this->assertFalse($user->invoice_use_personal_phone);
        $this->assertSame('+256', $user->invoice_phone_dial_code);
        $this->assertSame('787075772', $user->invoice_phone_national);
    }

    public function test_invoice_phone_customize_requires_dial_and_national(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('settings.invoice.phone.update'), [
                'invoice_use_personal_phone' => false,
                'invoice_phone_dial_code' => '',
                'invoice_phone_national' => '',
            ]);

        $response->assertSessionHasErrors(['invoice_phone_dial_code', 'invoice_phone_national']);
    }

    public function test_phone_for_invoice_returns_personal_when_using_personal(): void
    {
        $user = User::factory()->create([
            'phone' => '+256787075772',
            'invoice_use_personal_phone' => true,
        ]);

        $this->assertSame('+256787075772', $user->phoneForInvoice());
    }

    public function test_phone_for_invoice_returns_custom_number(): void
    {
        $user = User::factory()->create([
            'phone' => '+256787075772',
            'invoice_use_personal_phone' => false,
            'invoice_phone_dial_code' => '+44',
            'invoice_phone_national' => '2071234567',
        ]);

        $this->assertSame('+44 2071234567', $user->phoneForInvoice());
    }
}
