<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * السماح بتنفيذ الطلب
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
            ],

            'password' => [
                'required',
                'string',
            ],

            'country' => 'nullable|string|max:100',
        ];
    }

    /**
     * رسائل الخطأ
     */
    public function messages(): array
    {
        return [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',

            'password.required' => 'كلمة المرور مطلوبة.',
        ];
    }
}