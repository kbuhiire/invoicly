<?php

namespace App\Http\Requests;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $secondary = $this->input('amount_secondary');
        $this->merge([
            'amount_secondary' => $secondary === '' || $secondary === null ? null : $secondary,
            'currency_secondary' => $this->filled('currency_secondary')
                ? strtoupper((string) $this->input('currency_secondary'))
                : null,
        ]);
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
            'status' => ['required', Rule::enum(InvoiceStatus::class)],
            'currency' => ['required', 'string', 'size:3'],
            'amount_secondary' => ['nullable', 'numeric', 'min:0'],
            'currency_secondary' => ['nullable', 'string', 'size:3', 'required_with:amount_secondary'],
            'is_template' => ['sometimes', 'boolean'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,txt'],
            'remove_attachment' => ['sometimes', 'boolean'],
            'payment_details' => ['nullable', 'string', 'max:500'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $invoice = $this->route('invoice');

                if (! $invoice instanceof \App\Models\Invoice) {
                    return;
                }

                $requestedDraft = $this->input('status') === InvoiceStatus::Draft->value;

                // Finalizing is one-way: a sent/paid invoice (or one with
                // payments) can never be demoted back to draft.
                if ($requestedDraft && ! $invoice->status->isDraft()) {
                    $validator->errors()->add('status', 'A finalized invoice cannot be reverted to draft.');
                }

                if ($requestedDraft && $invoice->payments()->exists()) {
                    $validator->errors()->add('status', 'An invoice with recorded payments cannot be a draft.');
                }
            },
        ];
    }
}
