<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
