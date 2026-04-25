<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status?->value ?? $this->status,
            'issue_date' => $this->issue_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'period_from' => $this->period_from?->format('Y-m-d'),
            'period_to' => $this->period_to?->format('Y-m-d'),
            'currency' => $this->currency,
            'amount' => (float) $this->amount,
            'vat_amount' => $this->vat_amount !== null ? (float) $this->vat_amount : null,
            'amount_secondary' => $this->amount_secondary !== null ? (float) $this->amount_secondary : null,
            'currency_secondary' => $this->currency_secondary,
            'payer_memo' => $this->payer_memo,
            'payment_details' => $this->payment_details,
            'invoice_type' => $this->invoice_type,
            'vat_id' => $this->vat_id,
            'tax_id' => $this->tax_id,
            'is_template' => (bool) $this->is_template,
            'has_attachment' => (bool) $this->attachment_path,
            'pdf_url' => url("/api/v1/invoices/{$this->id}/pdf"),
            'client' => $this->whenLoaded('client', fn () => new ClientResource($this->client)),
            'line_items' => $this->whenLoaded('lineItems', fn () => LineItemResource::collection($this->lineItems)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
