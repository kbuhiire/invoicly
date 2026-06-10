<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditNoteRequest extends FormRequest
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
            'invoice_id' => [
                'nullable',
                'integer',
                Rule::exists('invoices', 'id')->where('user_id', $userId),
            ],
            'currency' => ['required', 'string', 'size:3'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999999.99'],
            'issue_date' => ['nullable', 'date'],
            'memo' => ['nullable', 'string', 'max:255'],
            'apply_immediately' => ['sometimes', 'boolean'],
        ];
    }
}
