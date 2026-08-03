<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class BrevoMailService
{
    /**
     * إرسال رسالة HTML عبر Brevo API
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
                return [
                    'success' => true,
                    'message' => 'تم إرسال البريد بنجاح عبر Brevo API',
                    'data' => $response->json()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'فشل الإرسال: ' . $response->body()
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'خطأ في الاتصال: ' . $e->getMessage()
            ];
        }
    }
}