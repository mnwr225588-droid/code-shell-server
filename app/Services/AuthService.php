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
        $isTeacher = Teacher::where('email', $data['email'])->exists() || ($data['user_type'] ?? '') === 'teacher';

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
            'email_verified_at' => $isTeacher ? now() : null,
        ]);

        $token = $user->createToken('CodeShell')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'user_type' => $isTeacher ? 'teacher' : 'student',
        ];
    }

    /**
     * Login User
     */
    public function login(array $data): array
    {
        // أولاً: نبحث في جدول المستخدمين (طلاب + أدمنز)
        $user = User::query()
            ->select('users.*')
            ->selectSub(
                Teacher::query()
                    ->selectRaw('1')
                    ->whereColumn('teachers.email', 'users.email')
                    ->limit(1),
                'is_teacher_account'
            )
            ->where('users.email', $data['email'])
            ->first();

        if ($user && Hash::check($data['password'], $user->password)) {
            // ربط الدولة تلقائياً عند تسجيل الدخول
            if (!empty($data['country']) && $user->country !== $data['country']) {
                $user->country = $data['country'];
                $user->save();
            }

            // فحص هل المستخدم مدرس لتفعيل بريده تلقائياً وعدم مطالبته بالتحقق
            $isTeacher = (bool) $user->getAttribute('is_teacher_account');
            $user->makeHidden('is_teacher_account');
            if ($isTeacher && !$user->email_verified_at) {
                $user->email_verified_at = now();
                $user->save();
            }

            // Keep the previous active token temporarily so its next request
            // can receive the explicit "logged in elsewhere" response.
            $previousTokenId = $user->current_token_id;
            $user->tokens()
                ->when($previousTokenId, fn ($query) => $query->where('id', '!=', $previousTokenId))
                ->delete();

            $tokenResult = $user->createToken('CodeShell');
            $token = $tokenResult->plainTextToken;
            $user->current_token_id = $tokenResult->accessToken->id;
            $user->save();

            // determination of user type: admin if is_admin=1, teacher if hasTeacher relationship or isTeacher, else student
            $userType = 'student';
            if ($user->is_admin) {
                $userType = 'admin';
            } elseif ($isTeacher) {
                $userType = 'teacher';
            }

            $user->user_type = $userType;

            return [
                'user' => $user,
                'token' => $token,
                'user_type' => $userType,
            ];
        }

        // ثانياً: نبحث في جدول المدرسين
        $teacher = Teacher::where('email', $data['email'])->first();

        if ($teacher && Hash::check($data['password'], $teacher->password)) {
            $previousTokenId = $teacher->current_token_id;
            $teacher->tokens()
                ->when($previousTokenId, fn ($query) => $query->where('id', '!=', $previousTokenId))
                ->delete();

            $tokenResult = $teacher->createToken('CodeShell');
            $token = $tokenResult->plainTextToken;
            $teacher->current_token_id = $tokenResult->accessToken->id;
            $teacher->save();

            $teacher->user_type = 'teacher';

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