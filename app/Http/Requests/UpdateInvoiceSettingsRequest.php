<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['invoice_show_email', 'invoice_show_phone', 'invoice_show_signature'] as $key) {
            if (! $this->has($key)) {
                continue;
            }
            $v = $this->input($key);
            if ($v === '0' || $v === 0 || $v === 'false' || $v === false) {
                $this->merge([$key => false]);
            } elseif ($v === '1' || $v === 1 || $v === 'true' || $v === true) {
                $this->merge([$key => true]);
            }
        }

        if ($this->has('invoice_note') && $this->input('invoice_note') === '') {
            $this->merge(['invoice_note' => null]);
        }

        if ($this->has('preferred_currency')) {
            $this->merge(['preferred_currency' => strtoupper((string) $this->input('preferred_currency'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'invoice_show_email' => ['required', 'boolean'],
            'invoice_show_phone' => ['required', 'boolean'],
            'invoice_show_signature' => ['required', 'boolean'],
            'invoice_signature_type' => ['nullable', 'string', Rule::in(['digital', 'custom'])],
            'invoice_note' => ['nullable', 'string', 'max:5000'],
            'invoice_type' => ['required', 'string', Rule::in(['service', 'product'])],
            'preferred_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'signature' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:3226'],
        ];
    }
}
