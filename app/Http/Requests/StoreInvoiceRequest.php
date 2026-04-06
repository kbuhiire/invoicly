<?php

namespace App\Http\Requests;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @var list<string>
     */
    private function newClientKeys(): array
    {
        return [
            'new_client_is_business',
            'new_client_first_name',
            'new_client_last_name',
            'new_client_business_name',
            'new_client_country',
            'new_client_street',
            'new_client_city',
            'new_client_postal_code',
            'new_client_email',
            'new_client_vat_number',
        ];
    }

    private function trimOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $t = trim($value);

        return $t === '' ? null : $t;
    }

    protected function prepareForValidation(): void
    {
        $secondary = $this->input('amount_secondary');
        $clientId = $this->input('client_id');
        $normalizedClientId = $clientId === '' || $clientId === null ? null : (int) $clientId;

        $vatAmount = $this->input('vat_amount');

        $merge = [
            'amount_secondary' => $secondary === '' || $secondary === null ? null : $secondary,
            'currency_secondary' => $this->filled('currency_secondary')
                ? strtoupper((string) $this->input('currency_secondary'))
                : null,
            'client_id' => $normalizedClientId,
            'vat_amount' => $vatAmount === '' || $vatAmount === null ? null : $vatAmount,
        ];

        if ($normalizedClientId !== null) {
            foreach ($this->newClientKeys() as $key) {
                $this->request->remove($key);
            }
        } else {
            $merge['new_client_first_name'] = $this->trimOrNull($this->input('new_client_first_name'));
            $merge['new_client_last_name'] = $this->trimOrNull($this->input('new_client_last_name'));
            $merge['new_client_business_name'] = $this->trimOrNull($this->input('new_client_business_name'));
            $merge['new_client_country'] = $this->trimOrNull($this->input('new_client_country'));
            $merge['new_client_street'] = $this->trimOrNull($this->input('new_client_street'));
            $merge['new_client_city'] = $this->trimOrNull($this->input('new_client_city'));
            $merge['new_client_postal_code'] = $this->trimOrNull($this->input('new_client_postal_code'));
            $merge['new_client_email'] = $this->trimOrNull($this->input('new_client_email'));
            $merge['new_client_vat_number'] = $this->trimOrNull($this->input('new_client_vat_number'));

            $b = $this->input('new_client_is_business');
            $merge['new_client_is_business'] = filter_var($b, FILTER_VALIDATE_BOOLEAN);
        }

        $this->merge($merge);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('client_id') && $this->has('new_client_is_business')) {
                $validator->errors()->add('client_id', 'Use either an existing client or new client details, not both.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = (int) $this->user()->id;

        $countryCodes = array_keys(config('countries'));

        $excludeIfExistingClient = Rule::excludeIf(fn() => $this->filled('client_id'));

        return [
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where('user_id', $userId),
                'required_without:new_client_is_business',
            ],
            'new_client_is_business' => [
                $excludeIfExistingClient,
                'required',
                'boolean',
            ],
            'new_client_first_name' => [
                $excludeIfExistingClient,
                'nullable',
                'string',
                'max:100',
                Rule::requiredIf(fn() => ! $this->filled('client_id') && ! $this->boolean('new_client_is_business')),
            ],
            'new_client_last_name' => [
                $excludeIfExistingClient,
                'nullable',
                'string',
                'max:100',
                Rule::requiredIf(fn() => ! $this->filled('client_id') && ! $this->boolean('new_client_is_business')),
            ],
            'new_client_business_name' => [
                $excludeIfExistingClient,
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn() => ! $this->filled('client_id') && $this->boolean('new_client_is_business')),
            ],
            'new_client_country' => [
                $excludeIfExistingClient,
                'required',
                'string',
                'size:2',
                Rule::in($countryCodes),
            ],
            'new_client_street' => [
                $excludeIfExistingClient,
                'required',
                'string',
                'max:255',
            ],
            'new_client_city' => [
                $excludeIfExistingClient,
                'required',
                'string',
                'max:100',
            ],
            'new_client_postal_code' => [
                $excludeIfExistingClient,
                'required',
                'string',
                'max:32',
            ],
            'new_client_email' => [
                $excludeIfExistingClient,
                'nullable',
                'email',
                'max:255',
            ],
            'new_client_vat_number' => [
                $excludeIfExistingClient,
                'nullable',
                'string',
                'max:64',
            ],
            'segment' => [
                Rule::excludeIf(fn() => $this->filled('client_id')),
                'required',
                Rule::in(['invoicly', 'external']),
            ],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(InvoiceStatus::class)],
            'currency' => ['required', 'string', 'size:3'],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'amount_secondary' => ['nullable', 'numeric', 'min:0'],
            'currency_secondary' => ['nullable', 'string', 'size:3', 'required_with:amount_secondary'],
            'payer_memo' => ['nullable', 'string', 'max:300'],
            'payment_details' => ['nullable', 'string', 'max:250'],
            'invoice_type' => ['nullable', 'string', 'max:100'],
            'vat_id' => ['nullable', 'string', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'is_template' => ['nullable', 'boolean'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,txt'],
            'identity_logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:3226'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
