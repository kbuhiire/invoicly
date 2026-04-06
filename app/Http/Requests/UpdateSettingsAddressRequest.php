<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsAddressRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $blank = [
            'line1' => null,
            'line2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => null,
        ];

        $merged = [];
        foreach (['personal_address', 'postal_address'] as $prefix) {
            $a = $this->input($prefix);
            if (! is_array($a)) {
                $merged[$prefix] = $blank;

                continue;
            }
            foreach (['line1', 'line2', 'city', 'region', 'postal_code', 'country_code'] as $field) {
                if (! array_key_exists($field, $a) || $a[$field] === '') {
                    $a[$field] = null;
                }
            }
            $merged[$prefix] = $a;
        }
        $this->merge($merged);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $countryCodes = array_keys(config('countries', []));

        $addressKeys = [
            'personal_address.line1' => ['nullable', 'string', 'max:500'],
            'personal_address.line2' => ['nullable', 'string', 'max:500'],
            'personal_address.city' => ['nullable', 'string', 'max:255'],
            'personal_address.region' => ['nullable', 'string', 'max:255'],
            'personal_address.postal_code' => ['nullable', 'string', 'max:50'],
            'personal_address.country_code' => ['nullable', 'string', 'size:2', Rule::in($countryCodes)],
            'postal_address.line1' => ['nullable', 'string', 'max:500'],
            'postal_address.line2' => ['nullable', 'string', 'max:500'],
            'postal_address.city' => ['nullable', 'string', 'max:255'],
            'postal_address.region' => ['nullable', 'string', 'max:255'],
            'postal_address.postal_code' => ['nullable', 'string', 'max:50'],
            'postal_address.country_code' => ['nullable', 'string', 'size:2', Rule::in($countryCodes)],
        ];

        return $addressKeys;
    }
}
