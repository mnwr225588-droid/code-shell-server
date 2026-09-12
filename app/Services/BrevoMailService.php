<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;
use Exception;

/// خدمة إرسال البريد الإلكتروني عبر Brevo.
/// 
/// الخدمة دي مسؤولة عن إرسال جميع رسائل البريد الإلكتروني في التطبيق:
/// 1. رسالة تأكيد البريد الإلكتروني عند التسجيل.
/// 2. رسالة إعادة تعيين كلمة المرور.
///
/// ⚠️ تم التحويل من Brevo HTTP API إلى SMTP لأن:
/// - Brevo API تتطلب تسجيل IP السيرفر في القائمة البيضاء.
/// - SMTP لا تحتاج تسجيل IP، تعمل بالبيانات الموجودة في .env مباشرة.
/// - بيانات SMTP موجودة بالفعل في .env (MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD).

class BrevoMailService
{
    /**
     * إرسال رسالة عامة عبر SMTP (Brevo relay).
     * 
     * @param string $toEmail البريد الإلكتروني للمستلم
     * @param string|null $toName اسم المستلم
     * @param string $subject عنوان الرسالة
     * @param string $htmlContent محتوى الرسالة بصيغة HTML
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendEmail($toEmail, $toName, $subject, $htmlContent)
    {
        try {
            Mail::html($htmlContent, function (Message $message) use ($toEmail, $toName, $subject) {
                $message->to($toEmail, $toName ?? 'مستخدم')
                        ->subject($subject);
            });

            Log::info("SMTP Mail: Email sent successfully to {$toEmail}");
            return ['success' => true, 'message' => 'تم إرسال البريد بنجاح'];
        } catch (Exception $e) {
            Log::error("SMTP Mail FAILED for {$toEmail}: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطأ في إرسال البريد: ' . $e->getMessage()];
        }
    }

    /**
     * إرسال رسالة تفعيل الحساب الاحترافية.
     * يُستدعى من AuthController عند التسجيل وعند إعادة إرسال التأكيد.
     *
     * @param string $toEmail بريد المستخدم
     * @param string $toName اسم المستخدم
     * @param string $activationUrl رابط التفعيل (يحتوي على التوكن)
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

    /**
     * إرسال رسالة إعادة تعيين كلمة المرور.
     * يُستدعى من AuthController عند طلب نسيان كلمة المرور.
     *
     * @param string $toEmail بريد المستخدم
     * @param string $toName اسم المستخدم
     * @param string $resetUrl رابط إعادة التعيين (يحتوي على التوكن)
     */
    public function sendPasswordResetEmail($toEmail, $toName, $resetUrl)
    {
        $htmlContent = '
            <div style="font-family: Tahoma, sans-serif; background-color: #f4f4f9; padding: 40px 0; direction: rtl;">
                <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                    <h2 style="color: #333; text-align: center;">منصة Code Shell</h2>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="color: #555; font-size: 16px;">مرحباً <strong>' . $toName . '</strong>،</p>
                    <p style="color: #555; font-size: 16px;">تلقينا طلباً لإعادة تعيين كلمة المرور الخاصة بحسابك. انقر على الزر أدناه لإنشاء كلمة مرور جديدة:</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . $resetUrl . '" style="background-color: #6366f1; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;">إعادة تعيين كلمة المرور</a>
                    </div>
                    <p style="color: #777; font-size: 14px;">إذا لم يعمل الزر، انسخ الرابط التالي وألصقه في متصفحك:</p>
                    <p style="word-break: break-all; background: #f9f9f9; padding: 10px; border-radius: 5px; font-size: 12px; color: #6366f1;"><a href="' . $resetUrl . '">' . $resetUrl . '</a></p>
                    <p style="color: #d9534f; font-size: 13px; margin-top: 20px;">⏱ تنبيه: هذا الرابط صالح لمدة 5 دقائق فقط ويُستخدم لمرة واحدة.</p>
                    <p style="color: #888; font-size: 13px;">إذا لم تطلب إعادة تعيين كلمة المرور، تجاهل هذه الرسالة وحسابك بأمان.</p>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="color: #999; font-size: 12px; text-align: center;">فريق دعم Code Shell</p>
                </div>
            </div>
        ';

        return $this->sendEmail($toEmail, $toName, '🔐 إعادة تعيين كلمة المرور - Code Shell', $htmlContent);
    }
}