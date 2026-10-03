<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramService
{
    protected string $botToken;

    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN', '');
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

        return Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
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
            $response = Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $user->telegram_chat_id,
                'text'    => "🔔 *{$title}*

{$body}",
                'parse_mode' => 'Markdown',
            ]);
            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
