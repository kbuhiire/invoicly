<?php

namespace App\Http\Requests;

use App\Services\DocumentNumberService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNumberingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('prefix')) {
            $this->merge(['prefix' => strtoupper(trim((string) $this->input('prefix')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::in(DocumentNumberService::TYPES)],
            'prefix' => ['required', 'string', 'regex:/^[A-Z][A-Z0-9]{0,11}$/'],
            'next_number' => ['required', 'integer', 'min:1', 'max:999999999'],
            'padding' => ['required', 'integer', 'between:0,6'],
            'include_year' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'prefix.regex' => 'Prefix must start with a letter and contain only uppercase letters and digits (max 12).',
        ];
    }
}
