<?php

/**
 * ====================================================
 * اسم الملف: AuthController.php
 * المسار: app/Http/Controllers/Api/AuthController.php
 * 
 * الوصف والمهمة الرئيسية:
 * هذا الملف هو العصب الأساسي لإدارة حسابات الطلاب والمدرسين والمصادقة الأسرية (Authentication API).
 * يتعامل مع عمليات التسجيل، تسجيل الدخول، استعادة كلمة المرور، تأكيد البريد الإلكتروني، وتغيير كلمة المرور.
 * 
 * ارتباطه بالمشروع:
 * - يستقبل الطلبات من موقع الويب (code_shell_web) وتطبيق الطالب والمدرس (code_shell_app / admin).
 * - يعتمد على Sanctum للرموز المحمية (Bearer Tokens).
 * - يعتمد على BrevoMailService لإرسال الرسائل الفورية عبر HTTP REST API.
 * ====================================================
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use App\Services\BrevoMailService;
use App\Models\EmailVerification;
use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * حقن خدمة AuthService المسؤولة عن منطق الأعمال البنيوي.
     */
    public function __construct(
        private AuthService $authService
    ) {}

    /**
     * ====================================================
     * 1. دالة إنشاء حساب جديد للطالب (Register)
     * ====================================================
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // إنشاء المستخدم وتأمين التوكن عبر AuthService
        $result = $this->authService->register($request->validated());
        $user = $result['user'];

        // توليد رمز تفعيل فريد وآمن
        $token = Str::random(64);

        EmailVerification::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addHours(24),
        ]);

        // محاولة إرسال بريد التفعيل عبر Brevo HTTP API
        try {
            $activationUrl = "https://code-shell-server-production.up.railway.app/verify-email/" . $token;
            $mailService = new BrevoMailService();
            $mailService->sendVerificationEmail($user->email, $user->name, $activationUrl);
        } catch (\Exception $e) {
            Log::warning("Register Mail Notice for {$user->email}: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الحساب بنجاح! يرجى مراجعة بريدك الإلكتروني لتأكيد الحساب.',
            'token' => $result['token'],
            'user' => $result['user'],
        ], 201);
    }

    /**
     * ====================================================
     * 2. دالة تسجيل الدخول (Login)
     * ====================================================
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'token' => $result['token'],
            'user' => $result['user'],
        ], 200);
    }

    /**
     * ====================================================
     * 3. دالة إعادة إرسال بريد التفعيل (Resend Verification Email)
     * ====================================================
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'حسابك مؤكد بالفعل.',
            ], 400);
        }

        // حذف رموز التفعيل القديمة
        EmailVerification::where('user_id', $user->id)->delete();

        // إنشاء رمز جديد
        $token = Str::random(64);
        EmailVerification::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addHours(24),
        ]);

        $activationUrl = "https://code-shell-server-production.up.railway.app/verify-email/" . $token;

        $mailService = new BrevoMailService();
        $mailResult = $mailService->sendVerificationEmail($user->email, $user->name, $activationUrl);

        if (!$mailResult['success']) {
            Log::warning("Resend verification warning for: {$user->email} — {$mailResult['message']}");
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال بريد التفعيل بنجاح. يرجى فحص صندوق الوارد (Inbox) ومجلد الرسائل غير المرغوب فيها (Spam).',
        ], 200);
    }

    /**
     * ====================================================
     * 4. دالة نسيت كلمة المرور (Forgot Password)
     * ====================================================
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'إذا كان البريد الإلكتروني مسجلاً لدينا، فستصلك رسالة تحتوي على رابط إعادة التعيين.',
            ], 200);
        }

        PasswordReset::where('email', $user->email)->delete();

        $token = Str::random(64);
        PasswordReset::create([
            'email' => $user->email,
            'token' => $token,
            'expires_at' => now()->addMinutes(15),
        ]);

        $resetUrl = "https://code-shell-server-production.up.railway.app/reset-password/{$token}";

        $mailService = new BrevoMailService();
        $mailResult = $mailService->sendPasswordResetEmail($user->email, $user->name, $resetUrl);

        if (!$mailResult['success']) {
            Log::warning("Forgot password mail notice for {$user->email}: " . $mailResult['message']);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني بنجاح (يرجى فحص مجلد Spam).',
        ], 200);
    }

    /**
     * ====================================================
     * 5. دالة تغيير كلمة المرور من الإعدادات (Change Password)
     * ====================================================
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'كلمة المرور الحالية غير صحيحة.',
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح.',
        ], 200);
    }

    /**
     * ====================================================
     * 6. دالة تسجيل الخروج (Logout)
     * ====================================================
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح.',
        ], 200);
    }

    /**
     * ====================================================
     * 7. دالة حذف الحساب نهائياً مع كلمة المرور (Delete Account)
     * ====================================================
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
            'reason' => 'nullable|string|max:255',
            'reason_text' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'كلمة المرور غير صحيحة.',
            ], 422);
        }

        Log::info("User account deleted: {$user->email}");

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف حسابك نهائياً.',
        ], 200);
    }
}