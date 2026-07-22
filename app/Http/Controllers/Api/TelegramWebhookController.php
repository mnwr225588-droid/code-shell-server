<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TelegramWebhookController extends Controller
{
    protected string $botToken;

    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN', '');
    }

    public function handle(Request $request)
    {
        $data = $request->all();
        Log::info('Telegram Webhook Payload:', $data);

        // 1. معالجة الرسائل والأوامر (/start USER_ID)
        if (isset($data['message'])) {
            $chatId = $data['message']['chat']['id'];
            $text = trim($data['message']['text'] ?? '');

            if (str_starts_with($text, '/start')) {
                $parts = explode(' ', $text);
                $userId = $parts[1] ?? null;

                if ($userId && is_numeric($userId)) {
                    $this->sendVerificationCard($chatId, $userId);
                } else {
                    $this->sendWelcomeMenu($chatId);
                }
            } else {
                $this->sendWelcomeMenu($chatId);
            }
        }

        // 2. معالجة ضغطات الأزرار التفاعلية (Callback Queries)
        if (isset($data['callback_query'])) {
            $callbackQuery = $data['callback_query'];
            $chatId = $callbackQuery['message']['chat']['id'];
            $callbackData = $callbackQuery['data'];
            $messageId = $callbackQuery['message']['message_id'];

            if (str_starts_with($callbackData, 'verify_')) {
                $userId = str_replace('verify_', '', $callbackData);
                $this->processAccountVerification($chatId, $userId, $messageId);
            } elseif (str_starts_with($callbackData, 'get_otp_')) {
                $userId = str_replace('get_otp_', '', $callbackData);
                $this->generateOtpCode($chatId, $userId, $messageId);
            } elseif ($callbackData === 'my_status') {
                $this->checkAccountStatus($chatId, $messageId);
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * جلب الاسم الكامل للمستخدم بدمج أجزاء الاسم
     */
    protected function getFullName($user): string
    {
        $nameParts = array_filter([
            $user->first_name ?? '',
            $user->middle_name ?? '',
            $user->last_name ?? ''
        ]);

        return !empty($nameParts) ? implode(' ', $nameParts) : ($user->name ?? 'مستخدم Code Shell');
    }

    /**
     * بطاقة توثيق الحساب الاحترافية (تظهر عند فتح البوت من التطبيق)
     */
    protected function sendVerificationCard($chatId, $userId)
    {
        $user = User::find($userId);

        if (!$user) {
            $this->sendMessage($chatId, "⚠️ *عفواً، لم نتمكن من العثور على هذا الحساب في قاعدة البيانات.*");
            return;
        }

        $fullName = $this->getFullName($user);
        $isVerified = $user->email_verified_at ? "✅ مفعل" : "❌ غير مفعل";

        $message = "🌐 *منصة Code Shell Security*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "مرحباً بك يا *{$fullName}* 👋\n\n";
        $message .= "📌 *بيانات الحساب:*\n";
        $message .= "• *الاسم الثلاثي:* {$fullName}\n";
        $message .= "• *البريد الإلكتروني:* `{$user->email}`\n";
        $message .= "• *حالة التفعيل:* {$isVerified}\n\n";
        $message .= "يرجى اختيار الإجراء المطلوب من الأزرار أدناه 👇";

        $buttons = [];

        if (!$user->email_verified_at) {
            $buttons[] = [
                ['text' => '✅ تأكيد وتفعيل هذا الحساب', 'callback_data' => 'verify_' . $user->id]
            ];
        }

        $buttons[] = [
            ['text' => '🔑 طلب كود تغيير كلمة السر (OTP)', 'callback_data' => 'get_otp_' . $user->id]
        ];

        $keyboard = ['inline_keyboard' => $buttons];

        $this->sendTelegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($keyboard)
        ]);
    }

    /**
     * معالجة ضغطة زر تفعيل الحساب
     */
    protected function processAccountVerification($chatId, $userId, $messageId)
    {
        $user = User::find($userId);

        if ($user) {
            $user->email_verified_at = now();
            $user->telegram_chat_id = $chatId;
            $user->save();

            $fullName = $this->getFullName($user);

            $successText = "🎉 *تم تأكيد وتفعيل بريدك الإلكتروني بنجاح!*\n";
            $successText .= "━━━━━━━━━━━━━━━━━━━\n\n";
            $successText .= "• *الاسم:* {$fullName}\n";
            $successText .= "• *البريد:* `{$user->email}`\n";
            $successText .= "• *الحالة:* مفعل ومربوط بالبوت ✅\n\n";
            $successText .= "يمكنك الآن العودة لتطبيق *Code Shell* والاستفادة من كافة الخدمات!";

            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🔑 طلب كود تغيير كلمة السر', 'callback_data' => 'get_otp_' . $user->id]]
                ]
            ];

            $this->sendTelegramApi('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $successText,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($keyboard)
            ]);
        }
    }

    /**
     * إنشاء كود OTP لتغيير كلمة السر من إعدادات التطبيق
     */
    protected function generateOtpCode($chatId, $userId, $messageId)
    {
        $user = User::find($userId);

        if ($user) {
            $otp = rand(100000, 999999);
            
            Cache::put('telegram_otp_' . $user->email, $otp, now()->addMinutes(10));

            $otpText = "🔐 *كود التحقق الخاص بك (OTP)*\n";
            $otpText .= "━━━━━━━━━━━━━━━━━━━\n\n";
            $otpText .= "رمز التغيير الخاص بك هو:\n";
            $otpText .= "👉 `{$otp}` 👈\n\n";
            $otpText .= "⏱️ *الرمز صالحة لمدة 10 دقائق فقط.*\n";
            $otpText .= "قم بنسخ الرقم وإدخاله في نافذة إعدادات البوت داخل التطبيق لتغيير كلمة السر.";

            $this->sendTelegramApi('sendMessage', [
                'chat_id' => $chatId,
                'text' => $otpText,
                'parse_mode' => 'Markdown',
            ]);
        }
    }

    /**
     * قائمة الترحيب العامة للبوت
     */
    protected function sendWelcomeMenu($chatId)
    {
        $text = "🤖 *أهلاً بك في بوت Code Shell الرسمي*\n\n";
        $text .= "هذا البوت مخصص لتأكيد الحسابات وإدارة الأمان الخاصة بتطبيق Code Shell.\n\n";
        $text .= "لفتح حسابك وتأكيده، يرجى الضغط على زر *تأكيد الحساب* من داخل التطبيق.";

        $this->sendTelegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ]);
    }

    protected function sendMessage($chatId, $text)
    {
        $this->sendTelegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ]);
    }

    protected function sendTelegramApi($method, $payload)
    {
        Http::post("https://api.telegram.org/bot{$this->botToken}/{$method}", $payload);
    }
}