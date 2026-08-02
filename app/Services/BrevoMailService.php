<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoMailService
{
    /**
     * إرسال بريد إلكتروني عبر Brevo API
     */
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlContent
    ): bool {

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'api-key' => env('BREVO_API_KEY'),
            'content-type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [

            'sender' => [
                'name'  => env('MAIL_FROM_NAME'),
                'email' => env('MAIL_FROM_ADDRESS'),
            ],

            'to' => [
                [
                    'email' => $toEmail,
                    'name'  => $toName,
                ]
            ],

            'subject' => $subject,

            'htmlContent' => $htmlContent,

        ]);

        if ($response->successful()) {

            Log::info('Brevo Email Sent', [
                'to' => $toEmail,
                'subject' => $subject,
            ]);

            return true;
        }

        Log::error('Brevo API Error', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    }
}