<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'number' => $this->number,
            'status' => $this->effectiveStatus(),
            'stored_status' => $this->status?->value ?? $this->status,
            'issue_date' => $this->issue_date?->format('Y-m-d'),
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'currency' => $this->currency,
            'amount' => (float) $this->amount,
            'vat_amount' => $this->vat_amount !== null ? (float) $this->vat_amount : null,
            'payer_memo' => $this->payer_memo,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'converted_invoice_id' => $this->converted_invoice_id,
            'client' => $this->whenLoaded('client', fn () => new ClientResource($this->client)),
            'line_items' => $this->whenLoaded('lineItems', fn () => LineItemResource::collection($this->lineItems)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
