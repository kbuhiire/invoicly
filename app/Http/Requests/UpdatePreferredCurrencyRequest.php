<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferredCurrencyRequest extends FormRequest
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
            'preferred_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
        ];
    }
}
