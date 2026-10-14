<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Services\LoginAttemptLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $limiter = app(LoginAttemptLimiter::class);
        $email = $request->input('email');
        $ipAddress = $request->ip();
        $lockoutSeconds = $limiter->lockoutSeconds($email, $ipAddress);

        if ($lockoutSeconds > 0) {
            return $this->loginLockoutResponse($lockoutSeconds);
        }

        $admin = Admin::where('email', $request->email)->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            $lockoutSeconds = $limiter->recordFailure($email, $ipAddress);
            if ($lockoutSeconds > 0) {
                return $this->loginLockoutResponse($lockoutSeconds);
            }

            return response()->json([
                'success' => false,
                'message' => 'بيانات الدخول غير صحيحة'
            ], 401);
        }

        $limiter->clear($email, $ipAddress);

        // ملاحظة: جلسات الأدمن متعددة ومستمرة — الدخول من نافذة/متصفح جديد
        // لا يُبطل جلسات الأدمن الأخرى (عكس جلسات الطلاب التي تُبطل تلقائياً)

        // إنشاء توكن خاص بحارس الـ admin باستخدام Sanctum
        $token = $admin->createToken('admin_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل دخول المشرف بنجاح',
            'token' => $token,
            'admin' => $admin
        ]);
    }

    private function loginLockoutResponse(int $seconds)
    {
        $minutes = (int) ceil($seconds / 60);

        return response()->json([
            'success' => false,
            'message' => "تم إيقاف محاولات تسجيل الدخول مؤقتاً بسبب تكرار البيانات الخاطئة. حاول مرة أخرى بعد {$minutes} دقيقة.",
            'retry_after' => $seconds,
        ], 429)->header('Retry-After', (string) $seconds);
    }
}