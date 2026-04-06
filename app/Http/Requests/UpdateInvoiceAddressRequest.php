<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceAddressRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $v = $this->input('invoice_use_personal_address');
        if ($v === '0' || $v === 0 || $v === 'false' || $v === false) {
            $this->merge(['invoice_use_personal_address' => false]);
        } elseif ($v === '1' || $v === 1 || $v === 'true' || $v === true) {
            $this->merge(['invoice_use_personal_address' => true]);
        }

        $blank = [
            'line1' => null,
            'line2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => null,
        ];

        $a = $this->input('invoice_address');
        if (! is_array($a)) {
            $this->merge(['invoice_address' => $blank]);

            return;
        }
        foreach (['line1', 'line2', 'city', 'region', 'postal_code', 'country_code'] as $field) {
            if (! array_key_exists($field, $a) || $a[$field] === '') {
                $a[$field] = null;
            }
        }
        $this->merge(['invoice_address' => $a]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $countryCodes = array_keys(config('countries', []));

        $base = [
            'invoice_use_personal_address' => ['required', 'boolean'],
        ];

        if ($this->boolean('invoice_use_personal_address')) {
            return $base;
        }

        return array_merge($base, [
            'invoice_address.line1' => ['nullable', 'string', 'max:500'],
            'invoice_address.line2' => ['nullable', 'string', 'max:500'],
            'invoice_address.city' => ['nullable', 'string', 'max:255'],
            'invoice_address.region' => ['nullable', 'string', 'max:255'],
            'invoice_address.postal_code' => ['nullable', 'string', 'max:50'],
            'invoice_address.country_code' => ['nullable', 'string', 'size:2', Rule::in($countryCodes)],
        ]);
    }
}
