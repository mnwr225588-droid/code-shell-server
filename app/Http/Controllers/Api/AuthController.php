<?php

namespace App\Http\Controllers\Api;

/// الـ AuthController
/// مسؤول عن كل عمليات المصادقة (Authentication) الخاصة بالطالب في التطبيق.
/// بيشمل: التسجيل، تسجيل الدخول، استعادة كلمة المرور، إعادة إرسال التأكيد، وتسجيل الخروج.
/// ⚠️ مهم: أي تعديل في الـ Response هنا هيأثر مباشرة على الـ AuthProvider في تطبيق Flutter.
/// الـ Controller ده بيعتمد على AuthService علشان ينفذ الـ Business Logic بعيد عن الـ HTTP Layer.

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use App\Services\BrevoMailService;
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
    public function __construct(
        private AuthService $authService
    ) {}

    /**
     * Register New User
     */
    /// دالة إنشاء حساب جديد (الطالب).
    /// بتستقبل البيانات وتعملها Validation عن طريق RegisterRequest.
    /// الخطوات:
    /// 1. بتنشئ الحساب عن طريق الـ AuthService.
    /// 2. بتعمل Token فريد لرسالة التأكيد وتخزنه في الداتا بيز.
    /// 3. بتبعت رسالة التفعيل باستخدام BrevoMailService.
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

        // 4. إرسال بريد التفعيل عبر خدمة Brevo — مع فحص النتيجة الفعلية
        $mailService = new BrevoMailService();
        $mailResult = $mailService->sendVerificationEmail($user->email, $user->name, $activationUrl);

        if (!$mailResult['success']) {
            // تسجيل الخطأ — الحساب أُنشئ لكن الرسالة لم تُرسل
            Log::error("User registered but verification email FAILED for: {$user->email} — {$mailResult['message']}");
        } else {
            Log::info("New user registered and verification email sent to: {$user->email}");
        }

        return response()->json([
            'success' => true,
            'message' => $mailResult['success']
                ? 'تم إنشاء الحساب بنجاح. يرجى التحقق من بريدك الإلكتروني لتفعيل الحساب.'
                : 'تم إنشاء الحساب لكن فشل إرسال بريد التفعيل. يرجى المحاولة لاحقاً من الإعدادات.',
            'email_sent' => $mailResult['success'],
            'token' => $result['token'],
            'user' => $result['user'],
        ], 201);
    }

    /**
     * Login User
     */
    /// دالة تسجيل الدخول.
    /// بتاخد الإيميل والباسورد وتعملهم Validation عن طريق LoginRequest.
    /// بترجع Token (Sanctum) اللي التطبيق بيحفظه وبيستخدمه عشان يكلم أي Protected Route.
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
            'user_type' => $result['user_type'],
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
        $htmlContent = '
            <div style="font-family: Tahoma, sans-serif; background-color: #f4f4f9; padding: 40px 0; direction: rtl;">
                <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                    <h2 style="color: #333; text-align: center;">منصة Code Shell</h2>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="color: #555; font-size: 16px;">مرحباً <strong>' . $user->name . '</strong>،</p>
                    <p style="color: #555; font-size: 16px;">شكراً لتسجيلك معنا. لإتمام تفعيل حسابك والبدء في استخدام المنصة، يرجى النقر على الزر أدناه:</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . $activationUrl . '" style="background-color: #28a745; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;">تأكيد البريد الإلكتروني</a>
                    </div>
                    <p style="color: #777; font-size: 14px;">إذا لم يعمل الزر معك، يمكنك نسخ الرابط التالي ولصقه في متصفحك:</p>
                    <p style="word-break: break-all; background: #f9f9f9; padding: 10px; border-radius: 5px; font-size: 12px; color: #007bff;"><a href="' . $activationUrl . '">' . $activationUrl . '</a></p>
                    <p style="color: #d9534f; font-size: 13px; margin-top: 20px;">تنبيه: هذا الرابط صالح لمدة 24 ساعة فقط ويستخدم لمرة واحدة.</p>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="color: #999; font-size: 12px; text-align: center;">إذا لم تقم بطلب هذا الحساب، يمكنك تجاهل هذه الرسالة تماماً. | فريق دعم Code Shell</p>
                </div>
            </div>
        ';

        $mailResult = $mailService->sendEmail($user->email, $user->name, 'تفعيل حسابك الشخصي في Code Shell', $htmlContent);

        // التحقق من نتيجة الإرسال الفعلية قبل إخبار المستخدم
        if (!$mailResult['success']) {
            Log::error("Resend verification email FAILED via API for: {$user->email} — {$mailResult['message']}");
            return response()->json([
                'success' => false,
                'message' => 'فشل إرسال بريد التفعيل. تأكد من صحة بريدك الإلكتروني وحاول مرة أخرى.',
            ], 500);
        }

        Log::info("Verification email re-sent via API to: {$user->email}");

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

    /**
     * Forgot Password - إرسال رابط إعادة تعيين كلمة المرور
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        // لأسباب أمنية نرد بنجاح حتى لو لم يوجد المستخدم
        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'إذا كان البريد مسجلاً، ستصلك رسالة خلال دقيقة.',
            ]);
        }

        // حذف طلبات إعادة التعيين السابقة لنفس المستخدم
        PasswordReset::where('email', $user->email)->delete();

        // توليد Token فريد وآمن
        $token = Str::random(64);

        PasswordReset::create([
            'email'      => $user->email,
            'token'      => $token,
            'expires_at' => now()->addMinutes(5),
        ]);

        $resetUrl = "https://code-shell-server-production.up.railway.app/reset-password/{$token}";

        $mailService = new BrevoMailService();
        $htmlContent = '
            <div style="font-family: Tahoma, sans-serif; background-color: #f4f4f9; padding: 40px 0; direction: rtl;">
                <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                    <h2 style="color: #333; text-align: center;">منصة Code Shell</h2>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="color: #555; font-size: 16px;">مرحباً <strong>' . $user->name . '</strong>،</p>
                    <p style="color: #555; font-size: 16px;">تلقينا طلباً لإعادة تعيين كلمة المرور الخاصة بحسابك. انقر على الزر أدناه لإنشاء كلمة مرور جديدة:</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . $resetUrl . '" style="background-color: #6366f1; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;">إعادة تعيين كلمة المرور</a>
                    </div>
                    <p style="color: #777; font-size: 14px;">إذا لم يعمل الزر،انسخ الرابط التالي وألصقه في متصفحك:</p>
                    <p style="word-break: break-all; background: #f9f9f9; padding: 10px; border-radius: 5px; font-size: 12px; color: #6366f1;"><a href="' . $resetUrl . '">' . $resetUrl . '</a></p>
                    <p style="color: #d9534f; font-size: 13px; margin-top: 20px;">⏱ تنبيه: هذا الرابط صالح لمدة 5 دقائق فقط ويُستخدم لمرة واحدة.</p>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="color: #888; font-size: 13px;">إذا لم تطلب إعادة تعيين كلمة المرور، تجاهل هذه الرسالة وحسابك بأمان.</p>
                    <p style="color: #999; font-size: 12px; text-align: center;">فريق دعم Code Shell</p>
                </div>
            </div>
        ';

        $mailResult = $mailService->sendEmail($user->email, $user->name, '🔐 إعادة تعيين كلمة المرور - Code Shell', $htmlContent);

        if (!$mailResult['success']) {
            Log::error("Password reset email FAILED via API for: {$user->email} — {$mailResult['message']}");
            return response()->json([
                'success' => false,
                'message' => 'فشل إرسال رابط إعادة تعيين كلمة المرور. حاول مرة أخرى أو تواصل مع الدعم.',
            ], 500);
        }

        Log::info("Password reset email sent via API to: {$user->email}");

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني.',
        ]);
    }

    /**
     * حذف الحساب نهائياً — يتطلب كلمة المرور الحالية.
     * يُحذف المستخدم وكل بياناته المرتبطة (التقدم، الإشعارات، الاشتراكات،
     * الحجوزات، المعاملات...) عبر الـ cascade في قاعدة البيانات.
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'password'    => 'required',
            'reason'      => 'nullable|string|max:255',
            'reason_text' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        // التحقق من صحة كلمة المرور قبل الحذف
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'كلمة المرور غير صحيحة.',
            ], 422);
        }

        // تسجيل سبب الحذف في الـ Logs للتحليل
        Log::info('Account deletion requested', [
            'user_id' => $user->id,
            'email'   => $user->email,
            'reason'  => $request->reason,
            'reason_text' => $request->reason_text,
        ]);

        // حذف كل التوكنات (Sanctum) قبل حذف الحساب
        $user->tokens()->delete();

        // الحذف النهائي — البيانات المرتبطة تُحذف تلقائياً عبر cascade
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف حسابك نهائياً.',
        ], 200);
    }
}