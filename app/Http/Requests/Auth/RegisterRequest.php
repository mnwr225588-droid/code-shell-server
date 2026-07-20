<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation Rules
     */
    public function rules(): array
    {
        return [

            'first_name' => 'required|string|max:100',

            'middle_name' => 'required|string|max:100',

            'last_name' => 'required|string|max:100',

            'birth_date' => 'required|date',

            'email' => 'required|email|unique:users,email',

            'phone' => 'nullable|string|max:20',

            'password' => 'required|string|min:8|confirmed',

        ];
    }

    /**
     * Custom Messages
     */
    public function messages(): array
    {
        return [

            'first_name.required' => 'الاسم الأول مطلوب.',

            'middle_name.required' => 'اسم الأب مطلوب.',

            'last_name.required' => 'اسم العائلة مطلوب.',

            'birth_date.required' => 'تاريخ الميلاد مطلوب.',

            'email.required' => 'البريد الإلكتروني مطلوب.',

            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',

            'email.unique' => 'هذا البريد الإلكتروني مستخدم بالفعل.',

            'password.required' => 'كلمة المرور مطلوبة.',

            'password.min' => 'يجب أن تكون كلمة المرور 8 أحرف على الأقل.',

            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',

        ];
    }
}