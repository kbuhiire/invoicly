<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'number' => $this->number,
            'status' => $this->status?->value ?? $this->status,
            'issue_date' => $this->issue_date?->format('Y-m-d'),
            'currency' => $this->currency,
            'amount' => (float) $this->amount,
            'memo' => $this->memo,
            'applied_at' => $this->applied_at?->toIso8601String(),
            'invoice_id' => $this->invoice_id,
            'client' => $this->whenLoaded('client', fn () => new ClientResource($this->client)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
