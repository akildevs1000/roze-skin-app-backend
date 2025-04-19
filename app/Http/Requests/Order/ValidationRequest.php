<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class ValidationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            "customer.first_name" => "required|max:255",
            "customer.last_name" => "required|max:255",
            "customer.email" => "nullable",
            "customer.phone" => "nullable",

            "shipping_address.address_1" => "nullable|max:255",
            "shipping_address.address_2" => "nullable|max:255",
            "shipping_address.city" => "nullable|max:255",
            "shipping_address.state" => "nullable|max:255",
            "shipping_address.postcode" => "nullable|max:255",
            "shipping_address.country" => "nullable|max:255",

            "billing_address.address_1" => "nullable|max:255",
            "billing_address.address_2" => "nullable|max:255",
            "billing_address.city" => "nullable|max:255",
            "billing_address.state" => "nullable|max:255",
            "billing_address.postcode" => "nullable|max:255",
            "billing_address.country" => "nullable|max:255",

            'user_id' => 'nullable|integer',
            'username' => 'nullable|string',
            'email' => 'nullable|email',
            'order_id' => 'required|integer',
            'order_date' => 'required|date_format:Y-m-d H:i:s',
            'order_status' => 'required',
            'currency' => 'required|string',
            'shipping_charges' => 'required|numeric',
            'total' => 'required|numeric',
            'payment_method' => 'required|string',
            'payment_method_title' => 'nullable|string',
            'shipping_method' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item' => 'required',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.rate' => 'required|numeric|min:0',
            'items.*.tax' => 'required|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',

            'business_source_id' => 'nullable',
            'delivery_service_id' => 'nullable',
            'tracking_number' => 'nullable|min:1|max:50',
            'paid_amount' => 'nullable',
        ];
    }
}
