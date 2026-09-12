<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * خدمة إرسال الإيميلات عبر API Brevo (REST).
 * تستخدم منفذ 443 ولا تتأثر بحجب منافذ SMTP (587, 2525, 465).
 */
class BrevoApiMailService
{
    const API_BASE = 'https://api.brevo.com/v3/smtp/emails';

    /**
     * إرسال إيميل عبر API
     *
     * @param string $toEmail بريد المستلم
     * @param string $toName اسم المستلم
     * @param string $subject عنوان الإيميل
     * @param string $htmlContent محتوى الإيميل (HTML)
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendEmail($toEmail, $toName, $subject, $htmlContent)
    {
        $apiKey = env('BREVO_API_KEY');
        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'مفتاح API Brevo غير معرف في .env'];
        }

        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ];

        $payload = json_encode([
            'sender' => [
                'name' => env('MAIL_FROM_NAME', 'Code Shell'),
                'email' => env('MAIL_FROM_ADDRESS', 'mnwr225588@gmail.com')
            ],
            'to' => [
                [
                    'email' => $toEmail,
                    'name' => $toName
                ]
            ],
            'subject' => $subject,
            'htmlContent' => $htmlContent
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::API_BASE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Log::error("Brevo API Connection Error: $curlError");
            return ['success' => false, 'message' => 'فشل الاتصال بخدمة Brevo'];
        }

        $responseData = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['message_id'])) {
            Log::info("Brevo API Email sent to $toEmail");
            return ['success' => true, 'message' => 'تم الإرسال successful'];
        } else {
            Log::error("Brevo API Error: HTTP $httpCode, Response: " . $response);
            return ['success' => false, 'message' => 'فشل الإرسال من Brevo'];
        }
    }
}