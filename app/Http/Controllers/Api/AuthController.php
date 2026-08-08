<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use App\Services\BrevoMailService;
use App\Models\EmailVerification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    /**
     * Register New User
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());
        $user = $result['user'];

        // 1. توليد Token عشوائي طويل وآمن
        $token = Str::random(64);

        // 2. تخزين الـ Token في الجدول المستقل بصلاحية 24 ساعة
        EmailVerification::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addHours(24),
        ]);

        // 3. بناء رابط التفعيل الآمن عبر HTTPS
        $activationUrl = "https://code-shell-server-production.up.railway.app/verify-email/" . $token;

        // 4. إرسال بريد التفعيل عبر خدمة Brevo
        $mailService = new BrevoMailService();
        $mailService->sendVerificationEmail($user->email, $user->name, $activationUrl);

        // 5. تسجيل العملية في الـ Logs
        Log::info("New user registered and verification email sent to: {$user->email}");

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الحساب بنجاح. يرجى التحقق من بريدك الإلكتروني لتفعيل الحساب.',
            'token' => $result['token'],
            'user' => $result['user'],
        ], 201);
    }

    /**
     * Login User
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());
        $user = $result['user'];

        // تم السماح بتسجيل الدخول لجميع الحسابات لإظهار نافذة تفعيل البريد في التطبيق
        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح.',
            'token' => $result['token'],
            'user' => $result['user'],
        ], 200);
    }

    /**
     * Resend Verification Email
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'الحساب مؤكد بالفعل.',
            ], 400);
        }

        // حذف أي رموز تفعيل قديمة لنفس المستخدم
        EmailVerification::where('user_id', $user->id)->delete();

        // توليد Token جديد
        $token = Str::random(64);

        EmailVerification::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addHours(24),
        ]);

        $activationUrl = "https://code-shell-server-production.up.railway.app/verify-email/" . $token;

        $mailService = new BrevoMailService();
        $mailService->sendVerificationEmail($user->email, $user->name, $activationUrl);

        Log::info("Verification email re-sent to: {$user->email}");

        return response()->json([
            'success' => true,
            'message' => 'تم إعادة إرسال بريد التفعيل بنجاح.',
        ], 200);
    }

    /**
     * Logout User
     */
    public function logout(): JsonResponse
    {
        $user = auth()->user();

        // مسح FCM Token لمنع وصول إشعارات لحساب تم تسجيل الخروج منه
        if ($user) {
            $user->fcm_token = null;
            $user->save();
        }

        $user->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح.',
        ]);
    }

    /**
     * حفظ توكن جهاز الطالب (FCM Token) لاستقبال الإشعارات.
     * يُرسل من تطبيق الطالب عند تسجيل الدخول/التسجيل وعند تجديد التوكن.
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $user = $request->user();
        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Token updated successfully',
        ]);
    }

    /**
     * Change Password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:8|confirmed',
        ]);

        $user = $request->user();

        // التحقق من صحة كلمة المرور الحالية
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'كلمة المرور الحالية غير صحيحة.',
            ], 422);
        }

        // تحديث كلمة المرور الجديدة
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح.',
        ], 200);
    }
}