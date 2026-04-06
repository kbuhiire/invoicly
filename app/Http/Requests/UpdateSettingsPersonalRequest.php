<?php

namespace App\Http\Requests;

use App\Enums\ContractorSubcategory;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsPersonalRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $optional = [
            'citizenship_country',
            'timezone',
            'tax_residence_country',
            'contractor_subcategory',
            'legal_first_name',
            'legal_last_name',
            'phone',
            'passport_id_number',
            'tax_id',
            'vat_id',
        ];
        $merged = [];
        foreach ($optional as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $merged[$key] = null;
            }
        }
        if ($this->has('date_of_birth') && $this->input('date_of_birth') === '') {
            $merged['date_of_birth'] = null;
        }
        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $countryCodes = array_keys(config('countries', []));

        return [
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'legal_first_name' => ['nullable', 'string', 'max:255'],
            'legal_last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'citizenship_country' => ['nullable', 'string', 'size:2', Rule::in($countryCodes)],
            'timezone' => ['nullable', 'string', 'max:64', 'timezone:all'],
            'tax_residence_country' => ['nullable', 'string', 'size:2', Rule::in($countryCodes)],
            'contractor_subcategory' => ['nullable', 'string', Rule::enum(ContractorSubcategory::class)],
            'passport_id_number' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'vat_id' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:3226'],
        ];
    }
}
