<?php

/**
 * ====================================================
 * اسم الملف: BrevoMailService.php
 * المسار: app/Services/BrevoMailService.php
 * الوصف والمهمة الرئيسية:
 * خدمة إرسال البريد الإلكتروني المركزية لمنصة Code Shell.
 * تتولى إرسال كافة رسائل البريد الإلكتروني في النظام:
 * 1. رسائل تأكيد وتفعيل البريد الإلكتروني عند التسجيل وإعادة الإرسال.
 * 2. رسائل استعادة وإعادة تعيين كلمة المرور عند النسيان.
 * 
 * آلية العمل الفائقة (High-Speed Reliability):
 * - تستخدم Brevo HTTP REST API (عبر HTTPS Port 443) بمهلة زمنية 6 ثوانٍ كحد أقصى.
 * - تمنع تماماً توقف السيرفر (Socket Hangs) أو تجاوز 30 ثانية (Maximum execution time exceeded).
 * ====================================================
 */

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;
use Exception;

class BrevoMailService
{
    /**
     * إرسال بريد إلكتروني عام بصيغة HTML.
     * يحاول أولاً الإرسال عبر Brevo HTTP REST API السريعة جداً.
     * وفي حال عدم توفر المفتاح، ينتقل للإرسال عبر SMTP المحمي كاحتياط.
     *
     * @param string $toEmail البريد الإلكتروني للمستلم
     * @param string|null $toName اسم المستلم
     * @param string $subject عنوان الرسالة
     * @param string $htmlContent محتوى الرسالة بصيغة HTML
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendEmail($toEmail, $toName, $subject, $htmlContent)
    {
        $apiKey = env('BREVO_API_KEY');
        $senderEmail = env('BREVO_SENDER_EMAIL', 'mnwr225588@gmail.com');
        $senderName = env('BREVO_SENDER_NAME', 'Code Shell');

        // 1. المحاولة الأولى: الإرسال الفوري عبر Brevo HTTP REST API (Port 443 HTTPS)
        if (!empty($apiKey)) {
            try {
                Log::info("Brevo API: Attempting HTTP REST mail send to {$toEmail}...");

                $response = Http::timeout(6)
                    ->withHeaders([
                        'api-key' => $apiKey,
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ])
                    ->post('https://api.brevo.com/v3/smtp/email', [
                        'sender' => [
                            'name' => $senderName,
                            'email' => $senderEmail,
                        ],
                        'to' => [
                            [
                                'email' => $toEmail,
                                'name' => $toName ?? 'مستخدم Code Shell',
                            ]
                        ],
                        'subject' => $subject,
                        'htmlContent' => $htmlContent,
                    ]);

                if ($response->successful()) {
                    Log::info("Brevo API Success: Email sent instantly to {$toEmail} (MessageId: " . ($response->json()['messageId'] ?? 'OK') . ")");
                    return ['success' => true, 'message' => 'تم إرسال البريد بنجاح عبر Brevo API'];
                }

                Log::warning("Brevo API Response Warning ({$response->status()}): " . $response->body());
            } catch (Exception $e) {
                Log::warning("Brevo HTTP API Connection Exception: " . $e->getMessage());
            }
        }

        // 2. المحاولة الثانية (Fallback): الإرسال عبر SMTP محمي بـ Try-Catch لعدم تعليق السيرفر
        try {
            Log::info("SMTP Fallback: Attempting SMTP mail send to {$toEmail}...");

            Mail::html($htmlContent, function (Message $message) use ($toEmail, $toName, $subject, $senderEmail, $senderName) {
                $message->from($senderEmail, $senderName)
                        ->to($toEmail, $toName ?? 'مستخدم')
                        ->subject($subject);
            });

            Log::info("SMTP Fallback Success: Email sent to {$toEmail}");
            return ['success' => true, 'message' => 'تم إرسال البريد بنجاح عبر SMTP'];

        } catch (Exception $e) {
            Log::error("Mail Send Error for {$toEmail}: " . $e->getMessage());
            return [
                'success' => false, 
                'message' => 'تعذر إرسال البريد الإلكتروني حالياً. يرجى التأكد من البريد والمحاولة لاحقاً.'
            ];
        }
    }

    /**
     * إرسال رسالة تفعيل وتأكيد البريد الإلكتروني.
     * 
     * @param string $toEmail بريد المستخدم
     * @param string $toName اسم المستخدم
     * @param string $activationUrl رابط التفعيل المحتوي على التوكن
     */
    public function sendVerificationEmail($toEmail, $toName, $activationUrl)
    {
        $htmlContent = '
            <div style="font-family: Tahoma, sans-serif; background-color: #f4f4f9; padding: 40px 0; direction: rtl;">
                <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                    <h2 style="color: #2563EB; text-align: center; font-size: 24px;">منصة Code Shell</h2>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="color: #333; font-size: 16px;">مرحباً <strong>' . htmlspecialchars($toName) . '</strong>،</p>
                    <p style="color: #555; font-size: 15px; line-height: 1.7;">شكراً لتسجيلك معنا في منصة Code Shell. لإتمام تفعيل حسابك والبدء في استكشاف الكورسات والتعلم، يرجى الضغط على الزر أدناه:</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . $activationUrl . '" style="background-color: #2563EB; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-size: 16px; font-weight: bold; display: inline-block;">تأكيد البريد الإلكتروني ✉️</a>
                    </div>
                    <p style="color: #777; font-size: 13px;">إذا لم يعمل الزر، يمكنك نسخ الرابط التالي ولصقه في المتصفح:</p>
                    <p style="word-break: break-all; background: #f9f9f9; padding: 10px; border-radius: 5px; font-size: 12px; color: #2563EB;"><a href="' . $activationUrl . '">' . $activationUrl . '</a></p>
                    <p style="color: #d9534f; font-size: 13px; margin-top: 20px;">⏱ تنبيه: هذا الرابط صالح لمدة 24 ساعة فقط.</p>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="color: #999; font-size: 12px; text-align: center;">إذا لم تطلب إنشاء هذا الحساب، يمكنك تجاهل هذه الرسالة. | فريق دعم Code Shell</p>
                </div>
            </div>
        ';

        return $this->sendEmail($toEmail, $toName, '✉️ تفعيل حسابك الشخصي في منصة Code Shell', $htmlContent);
    }

    /**
     * إرسال رسالة استعادة وإعادة تعيين كلمة المرور.
     * 
     * @param string $toEmail بريد المستخدم
     * @param string $toName اسم المستخدم
     * @param string $resetUrl رابط التعيين المحتوي على التوكن
     */
    public function sendPasswordResetEmail($toEmail, $toName, $resetUrl)
    {
        $htmlContent = '
            <div style="font-family: Tahoma, sans-serif; background-color: #f4f4f9; padding: 40px 0; direction: rtl;">
                <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                    <h2 style="color: #2563EB; text-align: center; font-size: 24px;">منصة Code Shell</h2>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="color: #333; font-size: 16px;">مرحباً <strong>' . htmlspecialchars($toName) . '</strong>،</p>
                    <p style="color: #555; font-size: 15px; line-height: 1.7;">تلقينا طلباً لإعادة تعيين كلمة المرور الخاصة بحسابك في منصة Code Shell. انقر على الزر أدناه لإنشاء كلمة مرور جديدة:</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . $resetUrl . '" style="background-color: #4F46E5; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-size: 16px; font-weight: bold; display: inline-block;">إعادة تعيين كلمة المرور 🔑</a>
                    </div>
                    <p style="color: #777; font-size: 13px;">إذا لم يعمل الزر، يمكنك نسخ الرابط التالي ولصقه في المتصفح:</p>
                    <p style="word-break: break-all; background: #f9f9f9; padding: 10px; border-radius: 5px; font-size: 12px; color: #4F46E5;"><a href="' . $resetUrl . '">' . $resetUrl . '</a></p>
                    <p style="color: #d9534f; font-size: 13px; margin-top: 20px;">⏱ تنبيه: هذا الرابط صالح لمدة 15 دقيقة فقط.</p>
                    <p style="color: #888; font-size: 13px;">إذا لم تطلب إعادة تعيين كلمة المرور، يمكنك تجاهل هذه الرسالة بأمان.</p>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="color: #999; font-size: 12px; text-align: center;">فريق دعم Code Shell</p>
                </div>
            </div>
        ';

        return $this->sendEmail($toEmail, $toName, '🔑 إعادة تعيين كلمة المرور — منصة Code Shell', $htmlContent);
    }
}