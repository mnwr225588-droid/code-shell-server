<?php

use Illuminate\Support\Facades\Route;
use App\Models\EmailVerification;
use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\PaymentController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/verify-email/{token}', function ($token) {
    // 1. البحث عن الـ Token في الجدول المستقل
    $verification = EmailVerification::where('token', $token)->first();

    // التحقق مما إذا كان الرابط غير صحيح أو تم استخدامه مسبقاً
    if (!$verification) {
        Log::warning("Verification attempt with invalid or already used token: {$token}");
        return view('auth.verify-result', [
            'status' => 'invalid',
            'message' => 'رابط التفعيل غير صحيح أو تم استخدامه مسبقاً.'
        ]);
    }

    // 2. التحقق من صلاحية المدة (24 ساعة)
    if (now()->greaterThan($verification->expires_at)) {
        Log::warning("Expired verification attempt for user ID: {$verification->user_id}");
        $verification->delete(); // حذف الـ Token المنتهي الصلاحية
        return view('auth.verify-result', [
            'status' => 'expired',
            'message' => 'عذراً، انتهت صلاحية رابط التفعيل (صالح لمدة 24 ساعة فقط).'
        ]);
    }

    $user = $verification->user;

    // 3. التحقق مما إذا كان الحساب مفعل مسبقاً
    if ($user->email_verified_at) {
        $verification->delete(); // تنظيف الـ Token الزائد
        return view('auth.verify-result', [
            'status' => 'already',
            'message' => 'هذا الحساب مُفعل مسبقاً بالفعل!'
        ]);
    }

    // 4. تفعيل الحساب بتسجيل وقت التفعيل في الحقل القياسي
    $user->email_verified_at = now();
    $user->save();

    // 5. استخدام الرابط لمرة واحدة فقط (حذف الـ Token من الجدول المستقل)
    $verification->delete();

    Log::info("Account successfully verified for user ID: {$user->id}");

    return view('auth.verify-result', [
        'status' => 'success',
        'message' => 'تم تأكيد وتفعيل بريدك الإلكتروني بنجاح تام! يمكنك الآن تسجيل الدخول إلى تطبيقك.'
    ]);
})->name('verification.verify');

// ── صفحة إعادة تعيين كلمة المرور (GET: عرض النموذج) ──
Route::get('/reset-password/{token}', function ($token) {
    $reset = PasswordReset::where('token', $token)->first();

    if (!$reset) {
        return view('auth.reset-password', ['invalid' => true, 'token' => '']);
    }

    if (now()->greaterThan($reset->expires_at)) {
        $reset->delete();
        return view('auth.reset-password', ['expired' => true, 'token' => '']);
    }

    return view('auth.reset-password', ['token' => $token]);
})->name('password.reset');

// ── صفحة إعادة تعيين كلمة المرور (POST: تطبيق التغيير) ──
Route::post('/reset-password/{token}', function (Request $request, $token) {
    $request->validate([
        'password'              => 'required|min:8|confirmed',
        'password_confirmation' => 'required',
    ]);

    $reset = PasswordReset::where('token', $token)->first();

    if (!$reset) {
        return view('auth.reset-password', ['invalid' => true, 'token' => '']);
    }

    if (now()->greaterThan($reset->expires_at)) {
        $reset->delete();
        return view('auth.reset-password', ['expired' => true, 'token' => '']);
    }

    $user = User::where('email', $reset->email)->first();

    if ($user) {
        $user->password = Hash::make($request->password);
        $user->save();
        Log::info("Password reset successfully for user: {$user->email}");
    }

    // حذف الـ Token بعد الاستخدام
    $reset->delete();

    return view('auth.reset-password', ['success' => true, 'token' => '']);
})->name('password.reset.post');

// صفحة الدفع التجريبية لمحاكي Sandbox (تستخدمها جلسة الدفع المؤقتة فقط)
Route::get('/api/payment/sandbox/{ref}', [PaymentController::class, 'sandboxPage'])
    ->name('payment.sandbox.page');

// صفحة إغلاق جلسة الدفع (تُفتح داخل WebView ثم يغلقها التطبيق)
Route::get('/api/payment/closed', function () {
    return view('payment.closed');
});