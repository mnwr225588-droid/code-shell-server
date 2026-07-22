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
}