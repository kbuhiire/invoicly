<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'template_invoice_id' => [
                'required',
                'integer',
                Rule::exists('invoices', 'id')->where('user_id', $this->user()->id),
            ],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'annually'])],
            'next_run_at' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'template_invoice_id.exists' => 'The selected invoice does not exist or does not belong to you.',
            'next_run_at.after_or_equal' => 'The start date must be today or in the future.',
        ];
    }
}
