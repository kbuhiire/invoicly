<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'invoice_id' => $this->invoice_id,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'reference' => $this->reference,
            'gateway' => $this->gateway,
            'external_id' => $this->external_id,
            'source' => $this->source?->value ?? $this->source,
            'match_status' => $this->match_status?->value ?? $this->match_status,
            'metadata' => $this->metadata,
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice ? new InvoiceResource($this->invoice) : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
