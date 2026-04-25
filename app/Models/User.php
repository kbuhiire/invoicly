<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\AddressNormalization;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'legal_first_name',
    'legal_last_name',
    'email',
    'password',
    'provider',
    'provider_id',
    'preferred_currency',
    'phone',
    'address',
    'country',
    'logo_path',
    'date_of_birth',
    'citizenship_country',
    'timezone',
    'tax_residence_country',
    'contractor_subcategory',
    'passport_id_number',
    'tax_id',
    'vat_id',
    'personal_address',
    'postal_address',
    'invoice_show_email',
    'invoice_show_phone',
    'invoice_note',
    'invoice_signature_path',
    'invoice_show_signature',
    'invoice_signature_type',
    'invoice_type',
    'invoice_use_personal_address',
    'invoice_address',
    'invoice_use_personal_phone',
    'invoice_phone_dial_code',
    'invoice_phone_national',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function recurringInvoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed:bcrypt',
            'date_of_birth' => 'date',
            'personal_address' => 'array',
            'postal_address' => 'array',
            'invoice_show_email' => 'boolean',
            'invoice_show_phone' => 'boolean',
            'invoice_show_signature' => 'boolean',
            'invoice_use_personal_address' => 'boolean',
            'invoice_address' => 'array',
            'invoice_use_personal_phone' => 'boolean',
        ];
    }

    /**
     * Address block shown on invoices (PDF / previews): profile or custom override.
     *
     * @return array<string, mixed>|null
     */
    public function issuerAddressForInvoice(): ?array
    {
        $personal = is_array($this->personal_address) ? $this->personal_address : null;

        if ($this->invoice_use_personal_address !== false) {
            return $personal;
        }

        $invoice = is_array($this->invoice_address) ? $this->invoice_address : null;
        if ($invoice !== null && AddressNormalization::isNonEmpty($invoice)) {
            return $invoice;
        }

        return $personal;
    }

    /**
     * Phone number shown on invoices: account number or custom override.
     */
    public function phoneForInvoice(): ?string
    {
        if ($this->invoice_use_personal_phone !== false) {
            return $this->phone ?: null;
        }

        $dial = trim((string) ($this->invoice_phone_dial_code ?? ''));
        $national = trim((string) ($this->invoice_phone_national ?? ''));

        if ($dial === '' && $national === '') {
            return $this->phone ?: null;
        }

        return trim($dial.' '.$national);
    }
}
