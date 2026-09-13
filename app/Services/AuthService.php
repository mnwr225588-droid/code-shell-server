<?php

namespace App\Services;

use App\Models\User;
use App\Models\Teacher;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Register New User
     */
    public function register(array $data): array
    {
        $user = User::create([

            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'],
            'last_name' => $data['last_name'],
            'birth_date' => $data['birth_date'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'country' => $data['country'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => true,

        ]);

        $token = $user->createToken('CodeShell')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'user_type' => 'student',
        ];
    }

    /**
     * Login User
     */
    public function login(array $data): array
    {
        // أولاً: نبحث في جدول المستخدمين (طلاب + أدمنز)
        $user = User::where('email', $data['email'])->first();

        if ($user && Hash::check($data['password'], $user->password)) {
            // ربط الدولة تلقائياً عند تسجيل الدخول
            if (!empty($data['country']) && $user->country !== $data['country']) {
                $user->country = $data['country'];
                $user->save();
            }

            $token = $user->createToken('CodeShell')->plainTextToken;

            // determination of user type: admin if is_admin=1, teacher if hasTeacher relationship, else student
            $userType = 'student';
            if ($user->is_admin) {
                $userType = 'admin';
            } elseif ($user->teacher !== null) {
                $userType = 'teacher';
            }

            return [
                'user' => $user,
                'token' => $token,
                'user_type' => $userType,
            ];
        }

        // ثانياً: نبحث في جدول المدرسين
        $teacher = Teacher::where('email', $data['email'])->first();

        if ($teacher && Hash::check($data['password'], $teacher->password)) {
            $token = $teacher->createToken('CodeShell')->plainTextToken;

            return [
                'user' => $teacher,
                'token' => $token,
                'user_type' => 'teacher',
            ];
        }

        throw ValidationException::withMessages([
            'email' => ['البريد الإلكتروني أو كلمة المرور غير صحيحة.']
        ]);
    }
}