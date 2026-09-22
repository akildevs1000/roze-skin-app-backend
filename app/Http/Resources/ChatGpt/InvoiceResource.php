<?php

namespace App\Http\Resources\ChatGpt;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'reference' => 'INV-' . str_pad($this->id, 6, '0', STR_PAD_LEFT),
            'status'    => $this->status,
            'issued_at' => $this->converted_to_invoice_at,
            'created_at' => optional($this->created_at)->toDateString(),
            'customer'  => new CustomerResource($this->whenLoaded('customer')),
            'order'     => $this->relationLoaded('order') && $this->order
                ? new OrderResource($this->order)
                : null,
        ];
    }
}
