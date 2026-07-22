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
    protected string $secretToken;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token', '');
        $this->secretToken = config('services.telegram.secret_token', '');
    }

    // ============================================================
    // 1. دوال إنشاء لوحات المفاتيح (Keyboards)
    // ============================================================

    /**
     * لوحة المفاتيح الخاصة ببطاقة التفعيل (تظهر عند /start)
     */
    protected function getVerificationKeyboard($token): array
    {
        return [
            'inline_keyboard' => [
                // الصف الأول: زر التفعيل الرئيسي (يأخذ عرضاً كاملاً)
                [
                    ['text' => '✅ تأكيد وربط الحساب', 'callback_data' => 'verify_' . $token]
                ],
                // الصف الثاني: زر مساعدة وزر فتح التطبيق (زرين في صف واحد)
                [
                    ['text' => '❓ كيف يعمل هذا البوت؟', 'callback_data' => 'help_verification'],
                    ['text' => '📱 فتح التطبيق', 'url' => 'https://codeshell.app/download'] // ضع رابط التطبيق هنا
                ]
            ]
        ];
    }

    /**
     * لوحة التحكم الرئيسية (تظهر بعد التفعيل)
     */
    protected function getMainMenuKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                // الصف الأول: طلب OTP + فتح صفحة تغيير كلمة السر (Web App)
                [
                    ['text' => '🔑 طلب كود OTP', 'callback_data' => 'get_otp'],
                    ['text' => '⚙️ تغيير كلمة السر', 'web_app' => ['url' => 'https://codeshell.app/settings/change-password']]
                ],
                // الصف الثاني: دعم فني + مشاركة البوت
                [
                    ['text' => '🆘 دعم فني', 'url' => 'https://t.me/CodeShell_Support'],
                    ['text' => '📣 مشاركة البوت', 'switch_inline_query' => 'جرب بوت CodeShell للأمان!']
                ],
                // الصف الثالث: إعادة إرسال البطاقة (في حال احتاجها)
                [
                    ['text' => '🔄 إعادة عرض بياناتي', 'callback_data' => 'show_my_card']
                ]
            ]
        ];
    }

    /**
     * لوحة خاصة بـ OTP (تظهر بعد طلب الكود)
     */
    protected function getOtpActionKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '📋 نسخ الكود (اضغط هنا)', 'callback_data' => 'copy_otp_trigger'], // يمكن التعامل معها من خلال نص Monospace
                    ['text' => '🏠 القائمة الرئيسية', 'callback_data' => 'back_to_menu']
                ]
            ]
        ];
    }

    /**
     * لوحة ترحيبية قبل التفعيل (للمستخدم الذي يفتح البوت بدون رابط)
     */
    protected function getWelcomeKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '📥 تحميل التطبيق', 'url' => 'https://codeshell.app/download'],
                    ['text' => '❓ التعليمات', 'callback_data' => 'help_welcome']
                ]
            ]
        ];
    }

    // ============================================================
    // 2. الوظائف الأساسية (معدلة لاستخدام الأزرار الجديدة)
    // ============================================================

    public function handle(Request $request)
    {
        // ... (نفس الكود الخاص بالتحقق من Secret Token)

        if (isset($data['message'])) {
            $chatId = $data['message']['chat']['id'];
            $text = trim($data['message']['text'] ?? '');

            if (str_starts_with($text, '/start')) {
                $parts = explode(' ', $text);
                $token = $parts[1] ?? null;
                if ($token) {
                    $this->processStartWithToken($chatId, $token);
                } else {
                    $this->sendWelcomeMenu($chatId);
                }
            } else {
                $this->sendWelcomeMenu($chatId);
            }
        }

        if (isset($data['callback_query'])) {
            $callbackQuery = $data['callback_query'];
            $chatId = $callbackQuery['message']['chat']['id'];
            $callbackData = $callbackQuery['data'];
            $messageId = $callbackQuery['message']['message_id'];

            // معالجة الأوامر الجديدة
            if ($callbackData === 'back_to_menu') {
                $this->sendMainMenu($chatId, $messageId);
            } elseif ($callbackData === 'show_my_card') {
                $this->resendUserCard($chatId, $messageId);
            } elseif ($callbackData === 'help_verification' || $callbackData === 'help_welcome') {
                $this->sendHelpMessage($chatId, $messageId);
            } elseif (str_starts_with($callbackData, 'verify_')) {
                $token = str_replace('verify_', '', $callbackData);
                $this->processAccountVerification($chatId, $token, $messageId);
            } elseif ($callbackData === 'get_otp') {
                $this->generateOtpCode($chatId, $messageId); // معدلة لتعديل الرسالة بدلاً من إرسال جديدة
            }
        }

        return response()->json(['status' => 'success']);
    }

    // ============================================================
    // 3. تحديث الوظائف لعرض الأزرار الجديدة
    // ============================================================

    protected function processStartWithToken($chatId, $token)
    {
        $userId = Cache::get('telegram_bind_token_' . $token);
        if (!$userId) {
            $this->sendMessage($chatId, "⚠️ *الرابط غير صالح أو انتهت صلاحيته!*");
            return;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->sendMessage($chatId, "⚠️ لم نتمكن من العثور على الحساب.");
            return;
        }

        // بناء الرسالة
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
        $message .= "اضغط على الزر أدناه لتأكيد ربط الحساب 👇";

        // استخدام لوحة المفاتيح الجديدة التي تحتوي على زر التفعيل + زر فتح التطبيق
        $this->sendTelegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($this->getVerificationKeyboard($token))
        ]);
    }

    protected function processAccountVerification($chatId, $token, $messageId)
    {
        $userId = Cache::get('telegram_bind_token_' . $token);
        if (!$userId) {
            $this->sendMessage($chatId, "⚠️ انتهت صلاحية الجلسة.");
            return;
        }

        $user = User::find($userId);
        if ($user) {
            // تفعيل وربط
            if (!$user->email_verified_at) {
                $user->email_verified_at = now();
            }
            $user->telegram_chat_id = $chatId;
            $user->save();

            Cache::forget('telegram_bind_token_' . $token);

            $fullName = e($this->getFullName($user));
            $email = e($user->email);

            $successText = "🎉 *تم التفعيل والربط بنجاح!*\n";
            $successText .= "━━━━━━━━━━━━━━━━━━━\n\n";
            $successText .= "• *الاسم:* {$fullName}\n";
            $successText .= "• *البريد:* `{$email}`\n";
            $successText .= "• *الحالة:* مفعل ✅\n\n";
            $successText .= "مرحباً بك في القائمة الرئيسية، اختر خدمتك من الأسفل.";

            // الآن نعرض القائمة الرئيسية بالأزرار المتقدمة
            $this->sendTelegramApi('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $successText,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($this->getMainMenuKeyboard())
            ]);
        }
    }

    protected function generateOtpCode($chatId, $messageId = null)
    {
        $user = User::where('telegram_chat_id', $chatId)->first();

        if (!$user) {
            $this->sendMessage($chatId, "⚠️ هذا الحساب غير مربوط!");
            return;
        }

        // Rate Limiting
        $rateKey = 'otp_rate_limit_' . $user->id;
        if (Cache::has($rateKey)) {
            $this->sendMessage($chatId, "⏳ *يرجى الانتظار دقيقة قبل طلب كود جديد.*");
            return;
        }

        $otp = random_int(100000, 999999);
        Cache::put('telegram_otp_' . $user->email, $otp, now()->addMinutes(10));
        Cache::put($rateKey, true, now()->addMinute());

        $otpText = "🔑 *كود تغيير كلمة السر (OTP)*\n";
        $otpText .= "━━━━━━━━━━━━━━━━━━━\n\n";
        $otpText .= "اضغط على الرقم لنسخه 👇\n\n";
        $otpText .= "`{$otp}`\n\n";
        $otpText .= "⏱️ صالح لمدة 10 دقائق.\n\n";
        $otpText .= "📱 *ملاحظة:* يمكنك أيضاً استخدام زر *تغيير كلمة السر* بالأسفل لفتح التطبيق مباشرة.";

        // دمج الأزرار: زر للقائمة الرئيسية + زر WebApp لتغيير كلمة السر
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '⚙️ فتح تطبيق (تغيير كلمة السر)', 'web_app' => ['url' => 'https://codeshell.app/settings/change-password']],
                    ['text' => '🏠 القائمة الرئيسية', 'callback_data' => 'back_to_menu']
                ]
            ]
        ];

        // إذا كان لدينا messageId (قادم من ضغط زر)، نعدل الرسالة الحالية، وإلا نرسل جديدة
        if ($messageId) {
            $this->sendTelegramApi('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $otpText,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($keyboard)
            ]);
        } else {
            $this->sendTelegramApi('sendMessage', [
                'chat_id' => $chatId,
                'text' => $otpText,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($keyboard)
            ]);
        }
    }

    // ============================================================
    // 4. دوال القائمة والمساعدة الجديدة
    // ============================================================

    protected function sendMainMenu($chatId, $messageId)
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        $name = $user ? e($this->getFullName($user)) : 'صديقنا';

        $text = "🏠 *القائمة الرئيسية*\n━━━━━━━━━━━━━━━━━━━\n\n";
        $text .= "مرحباً {$name}!\nاختر ما تريد فعله من الأزرار أدناه:\n\n";
        $text .= "• *OTP:* للحصول على كود تغيير كلمة السر.\n";
        $text .= "• *تغيير كلمة السر:* يفتح التطبيق مباشرة.\n";
        $text .= "• *الدعم:* للتواصل مع فريق الدعم.";

        $this->sendTelegramApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($this->getMainMenuKeyboard())
        ]);
    }

    protected function resendUserCard($chatId, $messageId)
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            $this->sendMessage($chatId, "⚠️ يرجى تفعيل الحساب أولاً.");
            return;
        }

        // إعادة عرض البطاقة لكن مع أزرار القائمة
        $fullName = e($this->getFullName($user));
        $email = e($user->email);
        $text = "🛡️ *بيانات حسابك*\n━━━━━━━━━━━━━━━━━━━\n\n";
        $text .= "• *الاسم:* {$fullName}\n";
        $text .= "• *البريد:* `{$email}`\n";
        $text .= "• *الحالة:* ✅ مفعل\n\n";

        $this->sendTelegramApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($this->getMainMenuKeyboard())
        ]);
    }

    protected function sendHelpMessage($chatId, $messageId)
    {
        $text = "❓ *كيفية الاستخدام*\n━━━━━━━━━━━━━━━━━━━\n\n";
        $text .= "1️⃣ قم بفتح تطبيق *Code Shell*.\n";
        $text .= "2️⃣ اذهب إلى الإعدادات واختر *ربط تليجرام*.\n";
        $text .= "3️⃣ اضغط على زر التفعيل ليصلك رابط.\n";
        $text .= "4️⃣ اضغط على الرابط واختر *تأكيد* لربط حسابك.\n\n";
        $text .= "🔐 بعد الربط، يمكنك طلب OTP بسهولة.";

        $this->sendTelegramApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
    }

    protected function sendWelcomeMenu($chatId)
    {
        $text = "🤖 *أهلاً بك في بوت Code Shell الرسمي*\n\n";
        $text .= "لربط حسابك وتفعيله، يرجى استخدام زر التفعيل من داخل تطبيق Code Shell مباشرة.";

        $this->sendTelegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($this->getWelcomeKeyboard())
        ]);
    }

    // ... (باقي الدوال المساعدة مثل sendMessage, sendTelegramApi, getFullName كما هي)
}