<?php

namespace App\Http\Requests\Customer;

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
            "customer.email" => "nullable|email|max:255",
            "customer.dob" => "nullable",
            "customer.phone" => "required|string|regex:/^\+?[0-9]{7,15}$/",
            "customer.whatsapp" => "nullable|string|regex:/^\+?[0-9]{7,15}$/",

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
        ];
    }
}
