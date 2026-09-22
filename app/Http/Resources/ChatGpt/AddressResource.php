<?php

namespace App\Http\Resources\ChatGpt;

use App\Support\ChatGpt\Pii;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'address_1' => Pii::street($this->address_1),
            'address_2' => Pii::street($this->address_2),
            'city'      => $this->city,
            'state'     => $this->state,
            'postcode'  => $this->postcode,
            'country'   => $this->country,
        ];
    }
}
