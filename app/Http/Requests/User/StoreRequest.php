<?php

namespace App\Http\Requests\User;

use App\Traits\failedValidationWithName;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    use failedValidationWithName;
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
        $arr = [
            'title' => 'required',
            'name' => 'required|min:3|max:100',
            'email' => 'required|min:3|max:191|unique:users',
            'password' => [
                'string',
                'confirmed',
                'min:6', // must be at least 10 characters in length
                'max:25', // must be maximum 25 characters in length
                'regex:/[a-z]/', // must contain at least one lowercase letter
                'regex:/[A-Z]/', // must contain at least one uppercase letter
                'regex:/[0-9]/', // must contain at least one digit
                'regex:/[@$!%*#?&]/', // must contain a special character
            ],
            // 'role_id' => 'required',
            // 'employee_role_id' => 'nullable',
            'company_id' => 'required',
            'mobile' => 'nullable',
            'image' => 'nullable',
            'is_active' => 'nullable',
            'last_name' => 'required',
            'enable_whatsapp_otp' => 'required',
        ];

        if ($this->user_type == "employee") {
            $arr["role_id"][] = "required";
            $arr["employee_role_id"][] = "nullable";
        }

        if ($this->user_type == "employee" || $this->user_type == "house_keeping" || $this->user_type == "maintenance") {
            $arr["password"][] = "required";
            $arr["user_type"][] = "required"; //employee,house_keeping,maintenance
        }

        return $arr;
    }

    public function messages()
    {
        return [
            "role_id.required" => "The role field is required.",
        ];
    }
}
