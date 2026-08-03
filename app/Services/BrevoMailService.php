<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class BrevoMailService
{
    /**
     * إرسال رسالة عامة عبر Brevo API
     */
    public function sendEmail($toEmail, $toName, $subject, $htmlContent)
    {
        $apiKey = env('BREVO_API_KEY');
        $senderEmail = env('BREVO_SENDER_EMAIL');
        $senderName = env('BREVO_SENDER_NAME');

        try {
            $response = Http::withHeaders([
                'accept' => 'application/json',
                'api-key' => $apiKey,
                'content-type' => 'application/json',
            ])->post('https://api.brevo.com/v3/smtp/email', [
                'sender' => [
                    'name' => $senderName,
                    'email' => $senderEmail,
                ],
                'to' => [
                    [
                        'email' => $toEmail,
                        'name' => $toName ?? 'مستخدم',
                    ]
                ],
                'subject' => $subject,
                'htmlContent' => $htmlContent,
            ]);

            if ($response->successful()) {
                Log::info("Brevo API: Email sent successfully to {$toEmail}");
                return ['success' => true, 'message' => 'تم إرسال البريد بنجاح'];
            } else {
                Log::error("Brevo API Failed for {$toEmail}: " . $response->body());
                return ['success' => false, 'message' => 'فشل الإرسال: ' . $response->body()];
            }
        } catch (Exception $e) {
            Log::error("Brevo API Exception for {$toEmail}: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطأ في الاتصال: ' . $e->getMessage()];
        }
    }

    /**
     * إرسال رسالة تفعيل الحساب الاحترافية
     */
    public function sendVerificationEmail($toEmail, $toName, $activationUrl)
    {
        $htmlContent = '
            <div style="font-family: Tahoma, sans-serif; background-color: #f4f4f9; padding: 40px 0; direction: rtl;">
                <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                    <h2 style="color: #333; text-align: center;">منصة Code Shell</h2>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="color: #555; font-size: 16px;">مرحباً <strong>' . $toName . '</strong>،</p>
                    <p style="color: #555; font-size: 16px;">شكراً لتسجيلك معنا. لإتمام تفعيل حسابك والبدء في استخدام المنصة، يرجى النقر على الزر أدناه:</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . $activationUrl . '" style="background-color: #28a745; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;">تأكيد البريد الإلكتروني</a>
                    </div>
                    <p style="color: #777; font-size: 14px;">إذا لم يعمل الزر معك، يمكنك نسخ الرابط التالي ولصقه في متصفحك:</p>
                    <p style="word-break: break-all; background: #f9f9f9; padding: 10px; border-radius: 5px; font-size: 12px; color: #007bff;"><a href="' . $activationUrl . '">' . $activationUrl . '</a></p>
                    <p style="color: #d9534f; font-size: 13px; margin-top: 20px;">تنبيه: هذا الرابط صالح لمدة 24 ساعة فقط ويستخدم لمرة واحدة.</p>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="color: #999; font-size: 12px; text-align: center;">إذا لم تقم بطلب هذا الحساب، يمكنك تجاهل هذه الرسالة تماماً. | فريق دعم Code Shell</p>
                </div>
            </div>
        ';

        return $this->sendEmail($toEmail, $toName, 'تفعيل حسابك الشخصي في Code Shell', $htmlContent);
    }
}