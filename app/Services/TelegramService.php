<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramService
{
    protected string $botToken;

    public function __construct()
    {
        $this->botToken = (string) config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN', '8210025097:AAHI0AXGYSAM7EoXjnGrCf3eZIL86X05e8U'));
    }

    /**
     * إرسال كود التحقق OTP إلى حساب التلجرام
     */
    public function sendOtp($chatId, $otpCode)
    {
        $message = "🔐 *Code Shell Verification Code*\n\n";
        $message .= "كود التحقق الخاص بك هو:\n";
        $message .= "`{$otpCode}`\n\n";
        $message .= "⏱️ *الكود صالح لمدة 5 دقائق فقط.*\n";
        $message .= "⚠️ لا تشارك هذا الكود مع أي شخص.";

        return Http::timeout(5)->connectTimeout(3)->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'MarkdownV2',
        ]);
    }

    /**
     * 🔔 إرسال إشعار المنصة إلى حساب تلجرام المستخدم (إذا كان مربوطاً).
     * يُستخدم كجسر: كل إشعار يُنشأ في المنصة يُرسل تلقائياً للبوت.
     */
    public function notify($user, string $title, string $body): bool
    {
        if (empty($this->botToken) || empty($user?->telegram_chat_id)) {
            return false;
        }

        try {
            $cleanTitle = e($title);
            $cleanBody = e($body);
            $text = "📣 <b>C O D E  S H E L L</b> │ <i>إشـعـار جـديـد</i>\n";
            $text .= "🔷 <b>══════════════════════════</b> 🔷\n\n";
            $text .= "🔔 <b>{$cleanTitle}</b>\n\n";
            $text .= "<blockquote>{$cleanBody}</blockquote>\n\n";
            $text .= "🔷 <b>══════════════════════════</b> 🔷\n";
            $text .= "✨ <i>Code Shell Platform</i>";

            $response = Http::timeout(4)->connectTimeout(2)->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id'    => $user->telegram_chat_id,
                'text'       => $text,
                'parse_mode' => 'HTML',
            ]);
            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
