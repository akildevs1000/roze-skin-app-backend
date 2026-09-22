<?php

namespace App\Http\Resources\ChatGpt;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full single-order view: summary fields plus line items, addresses and
 * invoice/payment state.
 */
class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        $summary = (new OrderSummaryResource($this->resource))->toArray($request);

        return $summary + [
            'shipping_charges'     => (float) $this->shipping_charges,
            'discount'             => (float) $this->discount,
            'shipping_method'      => $this->shipping_method,
            'special_instructions' => $this->special_instructions,
            'cancel_reason'        => $this->cancel_reason,
            'return_reason'        => $this->return_reason,
            'delivered_to'         => $this->delivered_to,
            'delivered_at'         => $this->delivered_at,
            'items'                => $this->lineItems(),
            'shipping_address'     => $this->address('shippingAddress', 'shipping_address'),
            'billing_address'      => $this->address('billingAddress', 'billing_address'),
            'invoice'              => $this->relationLoaded('invoice') && $this->invoice
                ? [
                    'reference' => 'INV-' . str_pad($this->invoice->id, 6, '0', STR_PAD_LEFT),
                    'status'    => $this->invoice->status,
                    'issued_at' => $this->invoice->converted_to_invoice_at,
                ]
                : null,
            'payments'             => $this->relationLoaded('payments')
                ? $this->payments->map(fn ($p) => [
                    'amount'    => (float) $p->paid_amount,
                    'status'    => $p->status,
                    'reference' => $p->payment_reference,
                    'date'      => optional($p->created_at)->toDateString(),
                ])->values()
                : null,
        ];
    }

    /**
     * Line items live in the `items` JSON snapshot taken when the order was
     * created. Older orders that predate it fall back to the order_items
     * table, which is keyed on the marketplace order_id (not the row id).
     */
    private function lineItems(): array
    {
        $items = is_array($this->items) ? $this->items : [];

        if ($items) {
            return collect($items)->map(fn ($i) => [
                'name'     => $i['item'] ?? ($i['name'] ?? null),
                'quantity' => (int) ($i['quantity'] ?? 0),
                'rate'     => (float) ($i['rate'] ?? 0),
                'total'    => (float) ($i['total'] ?? 0),
            ])->values()->all();
        }

        if ($this->relationLoaded('legacyItems')) {
            return $this->legacyItems->map(fn ($i) => [
                'name'     => $i->name,
                'quantity' => (int) $i->quantity,
                'rate'     => (float) $i->rate,
                'total'    => round((float) $i->rate * (int) $i->quantity, 2),
            ])->values()->all();
        }

        return [];
    }

    /**
     * Orders created before addresses were frozen onto the order have none of
     * their own — those fall back to the customer's current address, matching
     * what the rest of the app shows.
     */
    private function address(string $orderRelation, string $customerRelation)
    {
        $address = $this->{$orderRelation}
            ?: optional($this->customer)->{$customerRelation};

        return $address ? new AddressResource($address) : null;
    }
}
