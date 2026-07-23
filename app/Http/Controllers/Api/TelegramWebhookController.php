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
        // تعيين قيمة التوكن الافتراضية المباشرة لضمان عمل البوت حتى لو لم يقرأ السيرفر ملفات البيئة
        $this->botToken = config('services.telegram.bot_token') 
            ?: (env('TELEGRAM_BOT_TOKEN') ?: '8933818027:AAFwevQ0noapxwu2QwI0gcGQ01q2fPDinLc');

        $this->secretToken = config('services.telegram.secret_token') 
            ?: (env('TELEGRAM_SECRET_TOKEN') ?: '');
    }

    // ============================================================
    // 1. Keyboards
    // ============================================================

    protected function getVerificationKeyboard($token, $userId = null): array
    {
        $callbackPayload = $userId ? 'v_' . $userId : 'v_' . substr($token, 0, 30);

        return [
            'inline_keyboard' => [
                [
                    ['text' => '✅ تأكيد وربط الحساب', 'callback_data' => $callbackPayload]
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
                    ['text' => '💬 التواصل مع الدعم', 'callback_data' => 'show_support'],
                    ['text' => '🌐 مواقع التواصل الاجتماعي', 'callback_data' => 'show_social']
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
                    ['text' => 'واتساب الدعم الفني', 'url' => 'https://wa.me/201097167348'],
                    ['text' => 'Gmail', 'url' => 'https://mail.google.com/mail/?view=cm&fs=1&to=codeshell.dev@gmail.com']
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
                    ['text' => 'YouTube', 'url' => 'https://youtube.com/@CodeShell'], 
                    ['text' => 'TikTok', 'url' => 'https://tiktok.com/@CodeShell']
                ],
                [
                    ['text' => 'Facebook', 'url' => 'https://facebook.com/CodeShell'],
                    ['text' => 'Instagram', 'url' => 'https://instagram.com/CodeShell']
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
    // 2. Request Handling
    // ============================================================

    public function handle(Request $request)
    {
        $data = $request->all();

        // تسجيل الطلب الوارد للوج للتحقق والمتابعة
        Log::info("Telegram Webhook Request Received:", $data);

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
            } elseif (str_starts_with($callbackData, 'v_') || str_starts_with($callbackData, 'verify_')) {
                $payload = str_replace(['verify_', 'v_'], '', $callbackData);
                $this->processAccountVerification($chatId, $payload, $messageId);
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
                    $this->processStartWithToken($chatId, $token);
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
    // 3. Logic & Actions
    // ============================================================

    protected function processStartWithToken($chatId, $token)
    {
        $userId = Cache::get('telegram_bind_token_' . $token);
        
        if (!$userId && is_numeric($token)) {
            $userId = $token;
        }

        if (!$userId) {
            $this->sendMessage($chatId, "⚠️ *الرابط غير صالح أو انتهت صلاحيته!*");
            return;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->sendMessage($chatId, "⚠️ لم نتمكن من العثور على الحساب.");
            return;
        }

        Cache::put('telegram_token_user_' . $token, $user->id, now()->addHours(2));
        Cache::put('telegram_user_token_' . $user->id, $user->id, now()->addHours(2));

        $fullName = $this->escapeMarkdown($this->getFullName($user));
        $email = $this->escapeMarkdown($user->email);
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
            'reply_markup' => json_encode($this->getVerificationKeyboard($token, $user->id))
        ]);
    }

    protected function processAccountVerification($chatId, $payload, $messageId)
    {
        $userId = Cache::get('telegram_bind_token_' . $payload) 
               ?? Cache::get('telegram_token_user_' . $payload)
               ?? (is_numeric($payload) ? $payload : null);

        if (!$userId) {
            $this->sendTelegramApi('sendMessage', [
                'chat_id' => $chatId,
                'text' => "⚠️ *انتهت صلاحية الجلسة!*\nيرجى إعادة إرسال رابط التفعيل من التطبيق.",
                'parse_mode' => 'Markdown'
            ]);
            return;
        }

        $user = User::find($userId);
        if ($user) {
            if (!$user->email_verified_at) {
                $user->email_verified_at = now();
            }
            $user->telegram_chat_id = $chatId;
            $user->save();

            Cache::forget('telegram_bind_token_' . $payload);
            Cache::forget('telegram_token_user_' . $payload);

            $fullName = $this->escapeMarkdown($this->getFullName($user));
            $email = $this->escapeMarkdown($user->email);

            $successText = "🎉 *تم التفعيل والربط بنجاح!*\n";
            $successText .= "━━━━━━━━━━━━━━━━━━━\n\n";
            $successText .= "• *الاسم:* {$fullName}\n";
            $successText .= "• *البريد:* `{$email}`\n";
            $successText .= "• *الحالة:* مفعل ✅\n\n";
            $successText .= "مرحباً بك في القائمة الرئيسية، اختر خدمتك من الأسفل.";

            $res = $this->sendTelegramApi('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $successText,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($this->getMainMenuKeyboard())
            ]);

            if (!$res || !($res['ok'] ?? false)) {
                $this->sendTelegramApi('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $successText,
                    'parse_mode' => 'Markdown',
                    'reply_markup' => json_encode($this->getMainMenuKeyboard())
                ]);
            }
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
        $name = $user ? $this->escapeMarkdown($this->getFullName($user)) : 'صديقنا';

        $text = "🏠 *القائمة الرئيسية*\n━━━━━━━━━━━━━━━━━━━\n\n";
        $text .= "مرحباً {$name}!\nاختر ما تريد فعله من الأزرار أدناه:\n\n";
        $text .= "• *تغيير كلمة المرور:* للحصول على كود تغيير كلمة السر.\n";
        $text .= "• *الدعم:* للتواصل مع فريق الدعم.\n";
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

        $fullName = $this->escapeMarkdown($this->getFullName($user));
        $email = $this->escapeMarkdown($user->email);
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
    // 4. Utilities
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
                Log::error("Telegram API Error [{$method}]: " . $response->body());
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

    protected function escapeMarkdown(string $text): string
    {
        return str_replace(['_', '*', '`', '[', ']', '(', ')'], ['\_', '\*', '\`', '\[', '\]', '\(', '\)'], $text);
    }
}