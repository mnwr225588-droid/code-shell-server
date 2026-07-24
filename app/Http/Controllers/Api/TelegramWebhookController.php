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
    protected string $botToken = '8210025097:AAHI0AXGYSAM7EoXjnGrCf3eZIL86X05e8U';
    protected string $secretToken = '';

    public function __construct()
    {
        // تم تعيين التوكن مباشرة أعلى الكلاس لضمان العمل الفوري
    }

    // ============================================================
    // 0. دوال جلب روابط التليجرام الخاصة بالمستخدم (تطبيق Flutter)
    // ============================================================

    public function getBindUrl(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'المستخدم غير مسجل الدخول'
            ], 401);
        }

        // 1. توليد توكن عشوائي مؤقت للربط
        $token = Str::random(32);

        // 2. حفظ التوكن في الكاش لمدة 15 دقيقة مرتبطاً بـ ID المستخدم
        Cache::put('telegram_bind_token_' . $token, $user->id, now()->addMinutes(15));

        // 3. إنشاء رابط التليجرام الخاص بالبوت مع التوكن
        $telegramUrl = "https://t.me/codeshell_new_bot?start={$token}";

        return response()->json([
            'status' => 'success',
            'telegram_url' => $telegramUrl,
        ]);
    }

    public function getOtpUrl(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'المستخدم غير مسجل الدخول'
            ], 401);
        }

        if (!$user->telegram_chat_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'يجب ربط حسابك بتليجرام أولاً'
            ], 400);
        }

        // 1. توليد كود الـ OTP فوراً وحفظه
        $otp = random_int(100000, 999999);
        Cache::put('telegram_otp_' . $user->email, $otp, now()->addMinutes(10));

        // 2. توليد توكن مؤقت خاص بطلب الـ OTP عبر البوت
        $token = Str::random(32);
        Cache::put('telegram_otp_request_' . $token, [
            'user_id' => $user->id,
            'otp' => $otp
        ], now()->addMinutes(5));

        $telegramUrl = "https://t.me/codeshell_new_bot?start=otp_{$token}";

        return response()->json([
            'status' => 'success',
            'telegram_url' => $telegramUrl,
        ]);
    }

    // ============================================================
    // 1. دوال إنشاء لوحات المفاتيح (Keyboards)
    // ============================================================

    protected function getVerificationKeyboard($token): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '✅ تأكيد وربط الحساب', 'callback_data' => 'verify_' . $token]
                ],
                [
                    ['text' => '❓ كيف يعمل هذا البوت؟', 'callback_data' => 'help_verification']
                ]
            ]
        ];
    }

    protected function getMainMenuKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '👁️ عرض البريد (مؤقت)', 'callback_data' => 'reveal_email'],
                    ['text' => '🔑 تغيير كلمة المرور', 'callback_data' => 'get_otp']
                ],
                [
                    ['text' => '💬 التواصل مع الدعم', 'callback_data' => 'show_support'],
                    ['text' => '🌐 مواقع التواصل الاجتماعي', 'callback_data' => 'show_social']
                ],
                [
                    ['text' => '📣 مشاركة البوت', 'switch_inline_query' => 'جرب بوت CodeShell للأمان!']
                ]
            ]
        ];
    }

    protected function getEmailHiddenKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '🏠 القائمة الرئيسية', 'callback_data' => 'back_to_menu']
                ]
            ]
        ];
    }

    protected function getSupportKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '💬 واتساب الدعم الفني', 'url' => 'https://wa.me/201097167348'],
                    ['text' => '📧 Gmail', 'url' => 'https://mail.google.com/mail/?view=cm&fs=1&to=codeshell.dev@gmail.com']
                ],
                [
                    ['text' => '🏠 العودة للقائمة الرئيسية', 'callback_data' => 'back_to_menu']
                ]
            ]
        ];
    }

    protected function getSocialKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '▶️ YouTube', 'url' => 'https://youtube.com/@AsmahAI'], 
                    ['text' => '🌐 الموقع الرسمي', 'url' => 'https://codeshell.com']
                ],
                [
                    ['text' => '🏠 العودة للقائمة الرئيسية', 'callback_data' => 'back_to_menu']
                ]
            ]
        ];
    }

    protected function getWelcomeKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '❓ التعليمات', 'callback_data' => 'help_welcome']
                ]
            ]
        ];
    }

    // ============================================================
    // 2. الوظيفة الرئيسية ومعالجة الطلبات
    // ============================================================

    public function handle(Request $request)
    {
        if ($this->secretToken && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $this->secretToken) {
            Log::warning('Unauthorized Telegram Webhook attempt.');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->all();

        if (isset($data['callback_query'])) {
            $callbackQuery = $data['callback_query'];
            $callbackId = $callbackQuery['id'];
            $chatId = $callbackQuery['message']['chat']['id'];
            $callbackData = $callbackQuery['data'];
            $messageId = $callbackQuery['message']['message_id'];

            $this->sendTelegramApi('answerCallbackQuery', ['callback_query_id' => $callbackId]);

            if ($callbackData === 'back_to_menu') {
                $this->sendMainMenu($chatId, $messageId);
            } elseif ($callbackData === 'show_my_card') {
                $this->resendUserCard($chatId, $messageId);
            } elseif ($callbackData === 'reveal_email') {
                $this->revealEmailHandler($chatId, $messageId);
            } elseif ($callbackData === 'hide_email') {
                $this->sendMainMenu($chatId, $messageId);
            } elseif ($callbackData === 'help_verification' || $callbackData === 'help_welcome') {
                $this->sendHelpMessage($chatId, $messageId);
            } elseif ($callbackData === 'show_support') {
                $this->sendSupportMenu($chatId, $messageId);
            } elseif ($callbackData === 'show_social') {
                $this->sendSocialMenu($chatId, $messageId);
            } elseif (str_starts_with($callbackData, 'verify_')) {
                $token = str_replace('verify_', '', $callbackData);
                $this->processAccountVerification($chatId, $token, $messageId);
            } elseif ($callbackData === 'get_otp') {
                $this->generateOtpCode($chatId, $messageId);
            }

            return response()->json(['status' => 'success']);
        }

        if (isset($data['message'])) {
            $chatId = $data['message']['chat']['id'];
            $text = trim($data['message']['text'] ?? '');

            if (str_starts_with($text, '/start')) {
                $parts = explode(' ', $text);
                $token = $parts[1] ?? null;
                
                if ($token) {
                    if (str_starts_with($token, 'otp_')) {
                        // معالجة طلب كود تغيير كلمة المرور فوراً عند فتح الرابط
                        $realToken = str_replace('otp_', '', $token);
                        $this->processOtpRequestViaStart($chatId, $realToken);
                    } else {
                        // الربط العادي للحساب
                        $this->processStartWithToken($chatId, $token);
                    }
                } else {
                    $this->sendWelcomeMenu($chatId);
                }
            } else {
                $this->sendWelcomeMenu($chatId);
            }
        }

        return response()->json(['status' => 'success']);
    }

    // ============================================================
    // 3. دوال المعالجة والقوائم
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

        $fullName = e($this->getFullName($user));
        $maskedEmail = $this->maskEmail($user->email);
        $isVerified = $user->email_verified_at ? "✅ مفعل" : "❌ غير مفعل";

        $message = "🛡️ *مركز حماية Code Shell*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "مرحباً بك *{$fullName}* 👋\n\n";
        $message .= "📋 *بيانات الحساب:*\n";
        $message .= "• *الاسم:* {$fullName}\n";
        $message .= "• *البريد:* `{$maskedEmail}` *(مخفي للأمان)*\n";
        $message .= "• *التفعيل:* {$isVerified}\n\n";
        $message .= "اضغط على الزر أدناه لتأكيد ربط الحساب 👇";

        $this->sendTelegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($this->getVerificationKeyboard($token))
        ]);
    }

    protected function processOtpRequestViaStart($chatId, $token)
    {
        $data = Cache::get('telegram_otp_request_' . $token);
        if (!$data) {
            $this->sendMessage($chatId, "⚠️ *انتهت صلاحية طلب الكود، يرجى المحاولة مرة أخرى من التطبيق.*");
            return;
        }

        $user = User::find($data['user_id']);
        if (!$user) {
            $this->sendMessage($chatId, "⚠️ لم يتم العثور على الحساب المرتبط.");
            return;
        }

        $otp = $data['otp'];
        Cache::forget('telegram_otp_request_' . $token); // مسح التوكن لعدم إمكانية إعادة استخدامه

        $otpText = "🔑 *كود تغيير كلمة المرور*\n";
        $otpText .= "━━━━━━━━━━━━━━━━━━━\n\n";
        $otpText .= "مرحباً *{$this->getFullName($user)}* 👋\n";
        $otpText .= "إليك كود تغيير كلمة المرور الخاص بك، اضغط عليه لنسخه:\n\n";
        $otpText .= "`{$otp}`\n\n";
        $otpText .= "⏱️ صالح لمدة 10 دقائق.\n";
        $otpText .= "📱 قم بلصق الكود في تطبيق Code Shell للمتابعة.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🏠 القائمة الرئيسية', 'callback_data' => 'back_to_menu']
                ]
            ]
        ];

        $this->sendTelegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $otpText,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($keyboard)
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
            if (!$user->email_verified_at) {
                $user->email_verified_at = now();
            }
            $user->telegram_chat_id = $chatId;
            $user->save();

            Cache::forget('telegram_bind_token_' . $token);

            $fullName = e($this->getFullName($user));
            $maskedEmail = $this->maskEmail($user->email);

            $successText = "🎉 *تم التفعيل والربط بنجاح!*\n";
            $successText .= "━━━━━━━━━━━━━━━━━━━\n\n";
            $successText .= "• *الاسم:* {$fullName}\n";
            $successText .= "• *البريد:* `{$maskedEmail}`\n";
            $successText .= "• *الحالة:* مفعل ✅\n\n";
            $successText .= "مرحباً بك في القائمة الرئيسية، اختر خدمتك من الأسفل.";

            $this->sendTelegramApi('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $successText,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($this->getMainMenuKeyboard())
            ]);
        }
    }

    protected function revealEmailHandler($chatId, $messageId)
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            $this->sendMessage($chatId, "⚠️ حسابك غير مربوط!");
            return;
        }

        $fullName = e($this->getFullName($user));
        $fullEmail = e($user->email);

        $text = "🛡️ *بيانات حسابك (البريد مكشوف)*\n";
        $text .= "━━━━━━━━━━━━━━━━━━━\n\n";
        $text .= "• *الاسم:* {$fullName}\n";
        $text .= "• *البريد الكلي:* `{$fullEmail}`\n\n";
        $text .= "⏱️ *ملاحظة:* لأسباب أمنية، سيتم إخفاء البريد تلقائياً أو يمكنك العودة للقائمة فوراً.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔒 إخفاء البريد الآن', 'callback_data' => 'hide_email']
                ],
                [
                    ['text' => '🏠 القائمة الرئيسية', 'callback_data' => 'back_to_menu']
                ]
            ]
        ];

        $this->sendTelegramApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($keyboard)
        ]);
    }

    protected function generateOtpCode($chatId, $messageId = null)
    {
        $user = User::where('telegram_chat_id', $chatId)->first();

        if (!$user) {
            $this->sendMessage($chatId, "⚠️ هذا الحساب غير مربوط!");
            return;
        }

        $rateKey = 'otp_rate_limit_' . $user->id;
        if (Cache::has($rateKey)) {
            $this->sendMessage($chatId, "⏳ *يرجى الانتظار دقيقة قبل طلب كود جديد.*");
            return;
        }

        $otp = random_int(100000, 999999);
        Cache::put('telegram_otp_' . $user->email, $otp, now()->addMinutes(10));
        Cache::put($rateKey, true, now()->addMinute());

        $otpText = "🔑 *كود تغيير كلمة المرور*\n";
        $otpText .= "━━━━━━━━━━━━━━━━━━━\n\n";
        $otpText .= "اضغط على الرقم لنسخه 👇\n\n";
        $otpText .= "`{$otp}`\n\n";
        $otpText .= "⏱️ صالح لمدة 10 دقائق.\n\n";
        $otpText .= "📱 استخدم هذا الكود في تطبيق Code Shell لتغيير كلمة المرور.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🏠 القائمة الرئيسية', 'callback_data' => 'back_to_menu']
                ]
            ]
        ];

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

    protected function sendSupportMenu($chatId, $messageId)
    {
        $text = "💬 *مركز الدعم الفني*\n━━━━━━━━━━━━━━━━━━━\n\n";
        $text .= "اختر وسيلة التواصل المناسبة لك من الأزرار أدناه:";

        $this->sendTelegramApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($this->getSupportKeyboard())
        ]);
    }

    protected function sendSocialMenu($chatId, $messageId)
    {
        $text = "🌐 *منصاتنا على مواقع التواصل*\n━━━━━━━━━━━━━━━━━━━\n\n";
        $text .= "تابع حساباتنا الرسمية للحصول على أحدث التحديثات والأخبار:";

        $this->sendTelegramApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($this->getSocialKeyboard())
        ]);
    }

    protected function sendMainMenu($chatId, $messageId)
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        $name = $user ? e($this->getFullName($user)) : 'صديقنا';

        $text = "🏠 *القائمة الرئيسية*\n━━━━━━━━━━━━━━━━━━━\n\n";
        $text .= "مرحباً {$name}!\nاختر ما تريد فعله من الأزرار أدناه:\n\n";
        $text .= "• *عرض البريد:* لإظهار بريدك الإلكتروني المسجل.\n";
        $text .= "• *تغيير كلمة المرور:* للحصول على كود OTP لتغيير كلمة السر.\n";
        $text .= "• *الدعم & السوشيال ميديا:* للتواصل والمتابعة.";

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
        $text .= "🔐 بعد الربط، يمكنك عرض بريدك أو تغيير كلمة المرور بسهولة.";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🏠 القائمة الرئيسية', 'callback_data' => 'back_to_menu']]
            ]
        ];

        $this->sendTelegramApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($keyboard)
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

    protected function sendMessage($chatId, $text)
    {
        return $this->sendTelegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ]);
    }

    protected function sendTelegramApi(string $method, array $params = [])
    {
        if (empty($this->botToken)) {
            Log::error("Telegram Bot Token is missing!");
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$this->botToken}/{$method}", $params);
            return $response->json();
        } catch (\Exception $e) {
            Log::error("Telegram Exception [{$method}]: " . $e->getMessage());
            return false;
        }
    }

    protected function getFullName($user): string
    {
        return trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->name ?? 'مستخدم');
    }

    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return '****@****.com';
        
        $name = $parts[0];
        $domain = $parts[1];
        
        $maskedName = strlen($name) > 3 ? substr($name, 0, 3) . '****' : '***';
        return $maskedName . '@' . $domain;
    }
}