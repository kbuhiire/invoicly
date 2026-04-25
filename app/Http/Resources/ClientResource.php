<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type?->value ?? $this->type,
            'is_business' => (bool) $this->is_business,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'business_name' => $this->business_name,
            'country' => $this->country,
            'street' => $this->street,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'email' => $this->email,
            'vat_number' => $this->vat_number,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
