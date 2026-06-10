<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function isBusiness(): bool
    {
        return filter_var($this->input('is_business'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $countryCodes = array_keys(config('countries', []));

        $rules = [
            'type' => ['required', Rule::in(['invoicly', 'external'])],
            'is_business' => ['required', 'boolean'],
            'country' => ['required', 'string', 'size:2', Rule::in($countryCodes)],
            'street' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ];

        if ($this->isBusiness()) {
            $rules['business_name'] = ['required', 'string', 'max:255'];
            $rules['vat_number'] = ['nullable', 'string', 'max:64'];
        } else {
            $rules['first_name'] = ['required', 'string', 'max:100'];
            $rules['last_name'] = ['required', 'string', 'max:100'];
        }

        return $rules;
    }
}
