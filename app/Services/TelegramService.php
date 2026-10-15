<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected string $botToken;

    public function __construct()
    {
        $this->botToken = (string) config('services.telegram.bot_token');
    }

    /**
     * إرسال كود التحقق OTP إلى حساب التلجرام
     */
    public function sendOtp($chatId, $otpCode)
    {
        if ($this->botToken === '') {
            Log::critical('Telegram bot token is not configured.');
            return false;
        }

        $message = "🔐 *Code Shell Verification Code*\n\n";
        $message .= "كود التحقق الخاص بك هو:\n";
        $message .= "`{$otpCode}`\n\n";
        $message .= "⏱️ *الكود صالح لمدة 5 دقائق فقط.*\n";
        $message .= "⚠️ لا تشارك هذا الكود مع أي شخص.";

        try {
            $response = Http::timeout(5)->connectTimeout(3)->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'MarkdownV2',
            ]);
            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Telegram OTP delivery failed.', ['exception' => get_class($e)]);
            return false;
        }
    }

    /**
     * إرسال إشعار رسمي من المنصة إلى حساب تلجرام المستخدم المرتبط.
     */
    public function notify($user, string $title, string $body): bool
    {
        if ($this->botToken === '' || empty($user?->telegram_chat_id)) {
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
            if (!$response->successful()) {
                Log::warning('Telegram notification delivery failed.', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                ]);
            }
            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Telegram notification delivery failed.', [
                'user_id' => $user->id,
                'exception' => get_class($e),
            ]);
            return false;
        }
    }
}
