<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    protected $token = '8933818027:AAFwevQ0noapxwu2QwI0gcGQ01q2fPDinLc';

    public function handle(Request $request)
    {
        // استقبال البيانات القادمة من تليجرام
        $update = $request->all();

        // تسجيل البيانات في السجلات للتأكد من وصولها
        Log::info('Telegram Update: ', $update);

        if (isset($update['message'])) {
            $chatId = $update['message']['chat']['id'];
            $text = $update['message']['text'] ?? '';
            $username = $update['message']['from']['username'] ?? null;
            $firstName = $update['message']['from']['first_name'] ?? '';

            // معالجة الأمر /start
            if ($text === '/start') {
                $this->sendMessage($chatId, "أهلاً بك يا {$firstName} في منصة Code Shell! يرجى إرسال بريدك الإلكتروني لربط حسابك.");
            } else {
                // افتراضياً: إذا أرسل المستخدم بريده الإلكتروني أو أي نص لنقوم بربطه
                $this->handleUserResponse($chatId, $text);
            }
        }

        return response()->json(['status' => 'success']);
    }

    // دالة إرسال الرسائل عبر تليجرام
    protected function sendMessage($chatId, $message)
    {
        Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
        ]);
    }

    // دالة ربط المستخدم بقاعدة البيانات
    protected function handleUserResponse($chatId, $text)
    {
        // البحث عن المستخدم بالبريد الإلكتروني كمثال لربط الحساب
        $user = User::where('email', $text)->first();

        if ($user) {
            $user->update([
                'telegram_chat_id' => $chatId
            ]);
            $this->sendMessage($chatId, "تم ربط حسابك بنجاح يا أخي! 🎉");
        } else {
            $this->sendMessage($chatId, "عذراً، البريد الإلكتروني غير موجود في النظام. تأكد من كتابته بشكل صحيح.");
        }
    }
}