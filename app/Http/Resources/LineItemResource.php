<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LineItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'line_total' => round((float) $this->quantity * (float) $this->unit_price, 2),
            'sort_order' => $this->sort_order,
        ];
    }
}
