<?php

namespace App\Http\Requests\Invoice;

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
            'customer_id' => 'required|integer|min:1',
            'order_id' => 'required|integer|min:1',
            'business_source_id' => 'required|integer|min:1',
            'delivery_service_id' => 'required|integer|min:1',
            'tracking_number' => 'required|min:5|max:50',
            'status' => 'required|string',
        ];
    }
}
