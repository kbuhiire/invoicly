<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('currency')) {
            $this->merge(['currency' => strtoupper((string) $this->input('currency'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = (int) $this->user()->id;

        return [
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')->where('user_id', $userId),
            ],
            'issue_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', 'string', 'size:3'],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate_id' => ['nullable', 'integer', Rule::exists('tax_rates', 'id')->where('user_id', $userId)],
            'payer_memo' => ['nullable', 'string', 'max:300'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
