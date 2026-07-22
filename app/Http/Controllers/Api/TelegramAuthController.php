<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class TelegramAuthController extends Controller
{
    protected TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * ربط حساب التلجرام بالمستخدم عبر الـ Chat ID
     */
    public function linkTelegram(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'chat_id' => 'required|string',
        ]);

        $user = User::findOrFail($request->user_id);
        $user->telegram_chat_id = $request->chat_id;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'تم ربط حساب التلجرام بنجاح!'
        ]);
    }

    /**
     * إرسال كود الـ OTP لحساب التلجرام
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user->telegram_chat_id) {
            return response()->json([
                'status' => false,
                'message' => 'حسابك غير مربوط ببوت التلجرام بعد.'
            ], 400);
        }

        // توليد كود من 6 أرقام
        $otp = rand(100000, 999999);

        // حفظ الكود في Cache لمدة 5 دقائق
        Cache::put('otp_' . $user->id, $otp, now()->addMinutes(5));

        // إرسال الكود عبر البوت
        $this->telegramService->sendOtp($user->telegram_chat_id, $otp);

        return response()->json([
            'status' => true,
            'message' => 'تم إرسال كود التحقق إلى حسابك على التلجرام.'
        ]);
    }

    /**
     * تغيير كلمة السر بعد التحقق من الـ OTP
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|numeric',
            'password' => 'required|string|min:8|confirmed'
        ]);

        $user = User::where('email', $request->email)->first();
        $cachedOtp = Cache::get('otp_' . $user->id);

        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json([
                'status' => false,
                'message' => 'كود التحقق غير صحيح أو انتهت صلاحيته!'
            ], 422);
        }

        // تحديث كلمة السر وتصفير الـ OTP
        $user->password = Hash::make($request->password);
        $user->save();

        Cache::forget('otp_' . $user->id);

        return response()->json([
            'status' => true,
            'message' => 'تم تغيير كلمة السر بنجاح! يمكنك التسجيل الآن.'
        ]);
    }
}