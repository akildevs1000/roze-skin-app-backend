<?php

namespace App\Http\Resources\ChatGpt;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The shape used in lists and search results — enough to identify an order
 * without pulling in items or addresses.
 */
class OrderSummaryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'               => $this->id,
            'reference'        => 'ORD-' . str_pad($this->id, 6, '0', STR_PAD_LEFT),
            'order_no'         => (string) $this->order_id,
            'order_date'       => $this->order_date,
            'status'           => $this->order_status,
            'delivery_status'  => $this->delivery_status,
            'channel'          => $this->channel,
            'currency'         => $this->currency ?: 'AED',
            'total'            => (float) $this->total,
            'paid_amount'      => (float) $this->paid_amount,
            'balance'          => round((float) $this->total - (float) $this->paid_amount, 2),
            'payment_method'   => $this->payment_method_title ?: $this->payment_method,
            'tracking_number'  => $this->tracking_number,
            'delivery_service' => optional($this->delivery_service)->name,
            'business_source'  => optional($this->business_source)->name,
            'customer'         => new CustomerResource($this->whenLoaded('customer')),
        ];
    }
}
