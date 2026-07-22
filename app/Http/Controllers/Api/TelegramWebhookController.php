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
    protected string $secretToken;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token', '');
        $this->secretToken = config('services.telegram.secret_token', '');
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
                    ['text' => '🔑 تغيير كلمة المرور', 'callback_data' => 'get_otp'],
                    ['text' => '🔄 إعادة عرض بياناتي', 'callback_data' => 'show_my_card']
                ],
                [
                    ['text' => '📞 التواصل مع الدعم', 'callback_data' => 'show_support'],
                    ['text' => '🌐 مواقع التواصل', 'callback_data' => 'show_social']
                ],
                [
                    ['text' => '📣 مشاركة البوت', 'switch_inline_query' => 'جرب بوت CodeShell للأمان!']
                ]
            ]
        ];
    }

    protected function getSupportKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '💬 واتساب', 'url' => 'https://wa.me/201097167348'],
                    ['text' => '✉️ بريد إلكتروني', 'url' => 'mailto:codeshell.dev@gmail.com']
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
                    ['text' => '▶️ يوتيوب', 'url' => 'https://youtube.com/@CodeShell'], 
                    ['text' => '🎵 تيك توك', 'url' => 'https://tiktok.com/@CodeShell']
                ],
                [
                    ['text' => '📘 فيسبوك', 'url' => 'https://facebook.com/CodeShell'],
                    ['text' => '📸 إنستغرام', 'url' => 'https://instagram.com/CodeShell']
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
            $callbackId = $callbackQuery['id'];
            $chatId = $callbackQuery['message']['chat']['id'];
            $callbackData = $callbackQuery['data'];
            $messageId = $callbackQuery['message']['message_id'];

            $this->sendTelegramApi('answerCallbackQuery', ['callback_query_id' => $callbackId]);

            if ($callbackData === 'back_to_menu') {
                $this->sendMainMenu($chatId, $messageId);
            } elseif ($callbackData === 'show_my_card') {
                $this->resendUserCard($chatId, $messageId);
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
        }

        return response()->json(['status' => 'success']);
    }

    // ============================================================
    // 3. دوال المعالجة
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

    // ============================================================
    // 4. دوال القوائم الفرعية
    // ============================================================

    protected function sendSupportMenu($chatId, $messageId)
    {
        $text = "📞 *التواصل مع الدعم*\n━━━━━━━━━━━━━━━━━━━\n\n";
        $text .= "اختر وسيلة التواصل المناسبة لك:\n";
        $text .= "• *واتساب:* للدعم الفوري عبر المحادثة.\n";
        $text .= "• *البريد الإلكتروني:* للمراسلات الرسمية.";

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
        $text = "🌐 *تابعنا على مواقع التواصل*\n━━━━━━━━━━━━━━━━━━━\n\n";
        $text .= "انضم إلينا على منصاتنا الرسمية لتحصل على آخر التحديثات.";

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
        $text .= "• *تغيير كلمة المرور:* للحصول على كود تغيير كلمة السر.\n";
        $text .= "• *الدعم:* للتواصل مع فريق الدعم عبر واتساب أو البريد.\n";
        $text .= "• *مواقع التواصل:* لمتابعتنا على السوشيال ميديا.";

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
        $text .= "🔐 بعد الربط، يمكنك تغيير كلمة المرور بسهولة.";

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

    // ============================================================
    // 5. دوال المساعدة العامة
    // ============================================================

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
            
            if (!$response->successful()) {
                // تجنب تسجيل الأخطاء العادية الخاصة بضغط نفس الزر مرتين
                if (!str_contains($response->body(), 'message is not modified')) {
                    Log::error("Telegram API Error [{$method}]: " . $response->body());
                }
            }

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
}