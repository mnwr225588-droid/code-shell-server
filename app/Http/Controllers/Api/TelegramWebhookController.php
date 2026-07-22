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

        // 2. معالجة الضغط على الأزرار التفاعلية
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
                $this->generateOtpCode($chatId, $userId);
            }
        }

        return response()->json(['status' => 'success']);
    }

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
     * بطاقة تفعيل وتأكيد الحساب الرئيسية
     */
    protected function sendVerificationCard($chatId, $userId)
    {
        $user = User::find($userId);

        if (!$user) {
            $this->sendMessage($chatId, "⚠️ عفواً، لم نتمكن من العثور على هذا الحساب في النظام.");
            return;
        }

        $fullName = e($this->getFullName($user));
        $email = e($user->email);
        $isVerified = $user->email_verified_at ? "✅ مفعل" : "❌ غير مفعل";

        $message = "🛡️ *مركز حماية Code Shell*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "مرحباً بك *{$fullName}* 👋\n\n";
        $message .= "📋 *بيانات الحساب:*\n";
        $message .= "• *الاسم:* {$fullName}\n";
        $message .= "• *البريد:* `{$email}`\n";
        $message .= "• *التفعيل:* {$isVerified}\n\n";
        $message .= "اختر الإجراء المطلوب من الأزرار أدناه 👇";

        $buttons = [];

        if (!$user->email_verified_at) {
            $buttons[] = [
                ['text' => '✅ تفعيل البريد الإلكتروني', 'callback_data' => 'verify_' . $user->id]
            ];
        }

        $buttons[] = [
            ['text' => '🔑 طلب كود تغيير كلمة السر (OTP)', 'callback_data' => 'get_otp_' . $user->id]
        ];

        $this->sendTelegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]);
    }

    /**
     * معالجة تفعيل الحساب
     */
    protected function processAccountVerification($chatId, $userId, $messageId)
    {
        $user = User::find($userId);

        if ($user) {
            $user->email_verified_at = now();
            $user->telegram_chat_id = $chatId;
            $user->save();

            $fullName = e($this->getFullName($user));
            $email = e($user->email);

            $successText = "🎉 *تم تفعيل بريدك الإلكتروني بنجاح!*\n";
            $successText .= "━━━━━━━━━━━━━━━━━━━\n\n";
            $successText .= "• *الاسم:* {$fullName}\n";
            $successText .= "• *البريد:* `{$email}`\n";
            $successText .= "• *الحالة:* مفعل ✅\n\n";
            $successText .= "يمكنك الآن العودة لتطبيق *Code Shell* واستخدام كافة الخدمات!";

            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🔑 طلب كود تغيير كلمة السر (OTP)', 'callback_data' => 'get_otp_' . $user->id]]
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
     * إنشاء كود OTP وإرشاد المستخدم للتوجه للسيفر/التطبيق
     * الميزة: تتيح نسخ الكود بنقرة واحدة بفضل خاصية Monospace
     */
    protected function generateOtpCode($chatId, $userId)
    {
        $user = User::find($userId);

        if ($user) {
            $otp = rand(100000, 999999);
            
            // حفظ الكود لمدة 10 دقائق
            Cache::put('telegram_otp_' . $user->email, $otp, now()->addMinutes(10));

            $otpText = "🔑 *كود تغيير كلمة السر (OTP)*\n";
            $otpText .= "━━━━━━━━━━━━━━━━━━━\n\n";
            $otpText .= "انقر على الرقم أدناه لنسخه فوراً 👇\n\n";
            $otpText .= "`{$otp}`\n\n";
            $otpText .= "⏱️ *الرمز صالح لمدة 10 دقائق فقط.*\n\n";
            $otpText .= "📱 *خطوات الاستخدام:*\n";
            $otpText .= "1️⃣ اضغط على الرقم أعلاه لنسخه تلقائياً.\n";
            $otpText .= "2️⃣ افتح تطبيق *Code Shell* واذهب إلى *الإعدادات*.\n";
            $otpText .= "3️⃣ اضغط على *تغيير كلمة السر* وادخل الكود لتأكيده.";

            $this->sendTelegramApi('sendMessage', [
                'chat_id' => $chatId,
                'text' => $otpText,
                'parse_mode' => 'Markdown',
            ]);
        }
    }

    protected function sendWelcomeMenu($chatId)
    {
        $text = "🤖 *أهلاً بك في بوت Code Shell الرسمي*\n\n";
        $text .= "استخدم زر التفعيل من داخل التطبيق لفتح حسابك وتأكيده هنا تلقائياً.";

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