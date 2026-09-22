<?php

namespace App\Http\Resources\ChatGpt;

use App\Support\ChatGpt\Pii;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'           => $this->id,
            'reference'    => 'CST-' . str_pad($this->id, 6, '0', STR_PAD_LEFT),
            'name'         => trim($this->first_name . ' ' . $this->last_name),
            'phone'        => Pii::phone($this->phone),
            'whatsapp'     => Pii::phone($this->whatsapp),
            'email'        => Pii::email($this->email),
            'customer_since' => optional($this->created_at)->toDateString(),
        ];
    }
}
