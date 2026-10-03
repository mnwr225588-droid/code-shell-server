<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TelegramWebhookController extends Controller
{
    protected string $botToken;

    public function __construct()
    {
        $this->botToken = (string) config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN', '8210025097:AAHI0AXGYSAM7EoXjnGrCf3eZIL86X05e8U'));
    }

    /**
     * توليد رابط الربط الخاص بالمستخدم (يُستدعى من المنصة والتطبيق)
     */
    public function getBindUrl(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'المستخدم غير مسجل الدخول'
            ], 401);
        }

        // 1. توليد توكن عشوائي مؤقت للربط
        $token = Str::random(32);

        // 2. حفظ التوكن في الكاش لمدة 15 دقيقة مرتبطاً بـ ID المستخدم
        Cache::put('telegram_bind_token_' . $token, $user->id, now()->addMinutes(15));

        // 3. إنشاء رابط التليجرام المباشر للبوت مع التوكن
        $telegramUrl = "https://t.me/codeshell_new_bot?start={$token}";

        return response()->json([
            'status'       => 'success',
            'telegram_url' => $telegramUrl,
        ]);
    }

    /**
     * استقبال ومعالجة طلبات التليجرام (Webhook)
     */
    public function handle(Request $request)
    {
        $data = $request->all();

        // 1. معالجة الرسائل الواردة للبوت
        if (isset($data['message'])) {
            $chatId = $data['message']['chat']['id'] ?? null;
            $text   = trim($data['message']['text'] ?? '');

            if ($chatId) {
                if (str_starts_with($text, '/start')) {
                    $parts = explode(' ', $text);
                    $token = $parts[1] ?? null;

                    if ($token) {
                        // محاولة الربط باستخدام التوكن
                        $this->bindUserAccount($chatId, $token);
                    } else {
                        // إرسال معلومات الحساب المربوط أو التنبيه
                        $this->sendAccountStatus($chatId);
                    }
                } else {
                    $this->sendAccountStatus($chatId);
                }
            }
        }

        // 2. معالجة النقرات على الأزرار إن وجدت
        if (isset($data['callback_query'])) {
            $callbackQuery = $data['callback_query'];
            $callbackId    = $callbackQuery['id'];
            $chatId        = $callbackQuery['message']['chat']['id'];

            $this->sendTelegramApi('answerCallbackQuery', ['callback_query_id' => $callbackId]);
            $this->sendAccountStatus($chatId);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * ربط حساب الطالب فورياً عند فتح رابط البوت من المنصة
     */
    protected function bindUserAccount($chatId, string $token)
    {
        $userId = Cache::get('telegram_bind_token_' . $token);

        if (!$userId) {
            $this->sendMessage($chatId, "⚠️ <b>الرابط غير صالح أو انتهت صلاحيته!</b>\nيرجى الضغط على زر ربط البوت داخل المنصة مرة أخرى.");
            return;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->sendMessage($chatId, "⚠️ لم نتمكن من العثور على الحساب المرتبط.");
            return;
        }

        // حفظ chat_id وتأكيد البريد إن لم يكن مفعلاً
        if (!$user->email_verified_at) {
            $user->email_verified_at = now();
        }
        $user->telegram_chat_id = $chatId;
        $user->save();

        Cache::forget('telegram_bind_token_' . $token);

        $fullName    = e($this->getFullName($user));
        $maskedEmail = e($this->maskEmail($user->email));

        $text = "💎 <b>C O D E  S H E L L</b> │ <i>Security Bot</i>\n";
        $text .= "🔷 <b>══════════════════════════</b> 🔷\n\n";
        $text .= "🎉 <b>تـم ربـط الـحـسـاب وتـفـعـيـلـه بـنـجـاح!</b>\n\n";
        $text .= "📌 <b>بـيـانـات الـحـسـاب الـمُـوثّـقـة:</b>\n";
        $text .= "👤 <b>الاسم الثلاثي:</b> <code>{$fullName}</code>\n";
        $text .= "📧 <b>البريد المحمي:</b> <code>{$maskedEmail}</code>\n";
        $text .= "🟢 <b>الحالة التشغيلية:</b> <code>مـُتـصِـل ومـُفـعّـل ✅</code>\n\n";
        $text .= "<blockquote>⚡ <b>نـظـام الإشـعـارات الـلـحـظـيـة:</b>\nتصلك الآن إشعارات شحن المحفظة 💳، الاشتراكات 🎓، المحاضرات 🎥، والتحديثات الرسمية في هذه المحادثة فوراً.</blockquote>\n\n";
        $text .= "🔷 <b>══════════════════════════</b> 🔷\n";
        $text .= "✨ <i>Code Shell Platform — التميز والذكاء الرقمي</i>";

        $this->sendMessage($chatId, $text);
    }

    /**
     * إرسال حالة الربط الحالية عند مراسلة البوت
     */
    protected function sendAccountStatus($chatId)
    {
        $user = User::where('telegram_chat_id', $chatId)->first();

        if ($user) {
            $fullName    = e($this->getFullName($user));
            $maskedEmail = e($this->maskEmail($user->email));

            $text = "💎 <b>C O D E  S H E L L</b> │ <i>Smart Assistant</i>\n";
            $text .= "🔷 <b>══════════════════════════</b> 🔷\n\n";
            $text .= "👋 أهـلاً بـك <b>{$fullName}</b>\n\n";
            $text .= "📊 <b>حـالـة الـحـسـاب الـحـالـيـة:</b>\n";
            $text .= "👤 <b>المستخدم:</b> <code>{$fullName}</code>\n";
            $text .= "📧 <b>البريد:</b> <code>{$maskedEmail}</code>\n";
            $text .= "🟢 <b>الربط:</b> <code>مُـقـتـرن ومـُتـصِـل ⚡</code>\n\n";
            $text .= "<blockquote>💡 <b>خدمات البوت المفعلة:</b>\nاستلام كافة الإشعارات والعمليات المالية والرسائل الإدارية لحظياً دون تأخير.</blockquote>\n\n";
            $text .= "🔷 <b>══════════════════════════</b> 🔷\n";
            $text .= "✨ <i>Code Shell Platform</i>";
        } else {
            $text = "💎 <b>C O D E  S H E L L</b> │ <i>Smart Assistant</i>\n";
            $text .= "🔷 <b>══════════════════════════</b> 🔷\n\n";
            $text .= "⚠️ <b>حـسـابـك غـيـر مـربـوط مـع الـمـنـصـة!</b>\n\n";
            $text .= "<blockquote>📱 <b>خطوات التفعيل السريعة:</b>\nقم بفتح منصة <b>Code Shell</b>، واضغط على زر <b>'ربط بوت التلجرام'</b> ليتم اقتران حسابك فوراً واستلام جميع تنبيهاتك هنا.</blockquote>\n\n";
            $text .= "🔷 <b>══════════════════════════</b> 🔷\n";
            $text .= "✨ <i>Code Shell Platform</i>";
        }

        $this->sendMessage($chatId, $text);
    }

    /**
     * إرسال رسالة عبر التليجرام API باستخدام HTML
     */
    protected function sendMessage($chatId, string $text)
    {
        return $this->sendTelegramApi('sendMessage', [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML'
        ]);
    }

    /**
     * الاتصال بـ Telegram Bot API
     */
    protected function sendTelegramApi(string $method, array $params = [])
    {
        if (empty($this->botToken)) {
            Log::error("Telegram Bot Token is missing!");
            return false;
        }

        try {
            $response = Http::timeout(5)->connectTimeout(3)->post("https://api.telegram.org/bot{$this->botToken}/{$method}", $params);
            if (!$response->successful()) {
                Log::error("Telegram API Error [{$method}]: " . $response->body());
            }
            return $response->json();
        } catch (\Exception $e) {
            Log::error("Telegram Exception [{$method}]: " . $e->getMessage());
            return false;
        }
    }

    /**
     * جلب الاسم الثلاثي الكامل للمستخدم
     */
    protected function getFullName($user): string
    {
        $full = trim(($user->first_name ?? '') . ' ' . ($user->middle_name ?? '') . ' ' . ($user->last_name ?? ''));
        return $full !== '' ? $full : ($user->name ?? 'مستخدم المنصة');
    }

    /**
     * إخفاء جزء من البريد الإلكتروني للحفاظ على الخصوصية
     */
    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return '****@****.com';

        $name   = $parts[0];
        $domain = $parts[1];

        $maskedName = strlen($name) > 3 ? substr($name, 0, 3) . '****' : '***';
        return $maskedName . '@' . $domain;
    }
}