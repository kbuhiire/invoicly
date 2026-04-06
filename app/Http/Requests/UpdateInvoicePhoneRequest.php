<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoicePhoneRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $v = $this->input('invoice_use_personal_phone');
        if ($v === '0' || $v === 0 || $v === 'false' || $v === false) {
            $this->merge(['invoice_use_personal_phone' => false]);
        } elseif ($v === '1' || $v === 1 || $v === 'true' || $v === true) {
            $this->merge(['invoice_use_personal_phone' => true]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $base = [
            'invoice_use_personal_phone' => ['required', 'boolean'],
        ];

        if ($this->boolean('invoice_use_personal_phone')) {
            return $base;
        }

        return array_merge($base, [
            'invoice_phone_dial_code' => ['required', 'string', 'regex:/^\+\d{1,4}$/', 'max:8'],
            'invoice_phone_national'  => ['required', 'string', 'regex:/^[\d\s\-\(\)]+$/', 'max:30'],
        ]);
    }
}
