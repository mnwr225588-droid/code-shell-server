<?php

/**
 * ====================================================
 * Ø§Ø³Ù… Ø§Ù„Ù…Ù„Ù: BrevoMailService.php
 * Ø§Ù„Ù…Ø³Ø§Ø±: app/Services/BrevoMailService.php
 * Ø§Ù„ÙˆØµÙ ÙˆØ§Ù„Ù…Ù‡Ù…Ø© Ø§Ù„Ø±Ø¦ÙŠØ³ÙŠØ©:
 * Ø®Ø¯Ù…Ø© Ø¥Ø±Ø³Ø§Ù„ Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ Ø§Ù„Ù…Ø±ÙƒØ²ÙŠØ© Ù„Ù…Ù†ØµØ© Code Shell.
 * ØªØªÙˆÙ„Ù‰ Ø¥Ø±Ø³Ø§Ù„ ÙƒØ§ÙØ© Ø±Ø³Ø§Ø¦Ù„ Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ ÙÙŠ Ø§Ù„Ù†Ø¸Ø§Ù…:
 * 1. Ø±Ø³Ø§Ø¦Ù„ ØªØ£ÙƒÙŠØ¯ ÙˆØªÙØ¹ÙŠÙ„ Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ Ø¹Ù†Ø¯ Ø§Ù„ØªØ³Ø¬ÙŠÙ„ ÙˆØ¥Ø¹Ø§Ø¯Ø© Ø§Ù„Ø¥Ø±Ø³Ø§Ù„.
 * 2. Ø±Ø³Ø§Ø¦Ù„ Ø§Ø³ØªØ¹Ø§Ø¯Ø© ÙˆØ¥Ø¹Ø§Ø¯Ø© ØªØ¹ÙŠÙŠÙ† ÙƒÙ„Ù…Ø© Ø§Ù„Ù…Ø±ÙˆØ± Ø¹Ù†Ø¯ Ø§Ù„Ù†Ø³ÙŠØ§Ù†.
 * 
 * Ø¢Ù„ÙŠØ© Ø§Ù„Ø¹Ù…Ù„ Ø§Ù„ÙØ§Ø¦Ù‚Ø© (High-Speed Reliability):
 * - ØªØ³ØªØ®Ø¯Ù… Brevo HTTP REST API (Ø¹Ø¨Ø± HTTPS Port 443) Ø¨Ù…Ù‡Ù„Ø© Ø²Ù…Ù†ÙŠØ© 6 Ø«ÙˆØ§Ù†Ù ÙƒØ­Ø¯ Ø£Ù‚ØµÙ‰.
 * - ØªÙ…Ù†Ø¹ ØªÙ…Ø§Ù…Ø§Ù‹ ØªÙˆÙ‚Ù Ø§Ù„Ø³ÙŠØ±ÙØ± (Socket Hangs) Ø£Ùˆ ØªØ¬Ø§ÙˆØ² 30 Ø«Ø§Ù†ÙŠØ© (Maximum execution time exceeded).
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
     * Ø¥Ø±Ø³Ø§Ù„ Ø¨Ø±ÙŠØ¯ Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ Ø¹Ø§Ù… Ø¨ØµÙŠØºØ© HTML.
     * ÙŠØ­Ø§ÙˆÙ„ Ø£ÙˆÙ„Ø§Ù‹ Ø§Ù„Ø¥Ø±Ø³Ø§Ù„ Ø¹Ø¨Ø± Brevo HTTP REST API Ø§Ù„Ø³Ø±ÙŠØ¹Ø© Ø¬Ø¯Ø§Ù‹.
     * ÙˆÙÙŠ Ø­Ø§Ù„ Ø¹Ø¯Ù… ØªÙˆÙØ± Ø§Ù„Ù…ÙØªØ§Ø­ØŒ ÙŠÙ†ØªÙ‚Ù„ Ù„Ù„Ø¥Ø±Ø³Ø§Ù„ Ø¹Ø¨Ø± SMTP Ø§Ù„Ù…Ø­Ù…ÙŠ ÙƒØ§Ø­ØªÙŠØ§Ø·.
     *
     * @param string $toEmail Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ Ù„Ù„Ù…Ø³ØªÙ„Ù…
     * @param string|null $toName Ø§Ø³Ù… Ø§Ù„Ù…Ø³ØªÙ„Ù…
     * @param string $subject Ø¹Ù†ÙˆØ§Ù† Ø§Ù„Ø±Ø³Ø§Ù„Ø©
     * @param string $htmlContent Ù…Ø­ØªÙˆÙ‰ Ø§Ù„Ø±Ø³Ø§Ù„Ø© Ø¨ØµÙŠØºØ© HTML
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendEmail($toEmail, $toName, $subject, $htmlContent)
    {
        $apiKey = env('BREVO_API_KEY');
        $senderEmail = env('BREVO_SENDER_EMAIL', 'mnwr225588@gmail.com');
        $senderName = env('BREVO_SENDER_NAME', 'Code Shell');

        // 1. Ø§Ù„Ù…Ø­Ø§ÙˆÙ„Ø© Ø§Ù„Ø£ÙˆÙ„Ù‰: Ø§Ù„Ø¥Ø±Ø³Ø§Ù„ Ø§Ù„ÙÙˆØ±ÙŠ Ø¹Ø¨Ø± Brevo HTTP REST API (Port 443 HTTPS)
        if (!empty($apiKey)) {
            try {
                Log::info("Brevo API: Attempting HTTP REST mail send to {$toEmail}...");

                $response = Http::timeout(3)
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
                                'name' => $toName ?? 'Ù…Ø³ØªØ®Ø¯Ù… Code Shell',
                            ]
                        ],
                        'subject' => $subject,
                        'htmlContent' => $htmlContent,
                    ]);

                if ($response->successful()) {
                    Log::info("Brevo API Success: Email sent instantly to {$toEmail} (MessageId: " . ($response->json()['messageId'] ?? 'OK') . ")");
                    return ['success' => true, 'message' => 'ØªÙ… Ø¥Ø±Ø³Ø§Ù„ Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø¨Ù†Ø¬Ø§Ø­ Ø¹Ø¨Ø± Brevo API'];
                }

                Log::warning("Brevo API Response Warning ({$response->status()}): " . $response->body());
            } catch (Exception $e) {
                Log::warning("Brevo HTTP API Connection Exception: " . $e->getMessage());
            }
        }

        // 2. Ø§Ù„Ù…Ø­Ø§ÙˆÙ„Ø© Ø§Ù„Ø«Ø§Ù†ÙŠØ© (Fallback): Ø§Ù„Ø¥Ø±Ø³Ø§Ù„ Ø¹Ø¨Ø± SMTP Ù…Ø­Ù…ÙŠ Ø¨Ù€ Try-Catch Ù„Ø¹Ø¯Ù… ØªØ¹Ù„ÙŠÙ‚ Ø§Ù„Ø³ÙŠØ±ÙØ±
        try {
            Log::info("SMTP Fallback: Attempting SMTP mail send to {$toEmail}...");

            Mail::html($htmlContent, function (Message $message) use ($toEmail, $toName, $subject, $senderEmail, $senderName) {
                $message->from($senderEmail, $senderName)
                        ->to($toEmail, $toName ?? 'Ù…Ø³ØªØ®Ø¯Ù…')
                        ->subject($subject);
            });

            Log::info("SMTP Fallback Success: Email sent to {$toEmail}");
            return ['success' => true, 'message' => 'ØªÙ… Ø¥Ø±Ø³Ø§Ù„ Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø¨Ù†Ø¬Ø§Ø­ Ø¹Ø¨Ø± SMTP'];

        } catch (Exception $e) {
            Log::error("Mail Send Error for {$toEmail}: " . $e->getMessage());
            return [
                'success' => false, 
                'message' => 'ØªØ¹Ø°Ø± Ø¥Ø±Ø³Ø§Ù„ Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ Ø­Ø§Ù„ÙŠØ§Ù‹. ÙŠØ±Ø¬Ù‰ Ø§Ù„ØªØ£ÙƒØ¯ Ù…Ù† Ø§Ù„Ø¨Ø±ÙŠØ¯ ÙˆØ§Ù„Ù…Ø­Ø§ÙˆÙ„Ø© Ù„Ø§Ø­Ù‚Ø§Ù‹.'
            ];
        }
    }

    /**
     * Ø¥Ø±Ø³Ø§Ù„ Ø±Ø³Ø§Ù„Ø© ØªÙØ¹ÙŠÙ„ ÙˆØªØ£ÙƒÙŠØ¯ Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ.
     * 
     * @param string $toEmail Ø¨Ø±ÙŠØ¯ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
     * @param string $toName Ø§Ø³Ù… Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
     * @param string $activationUrl Ø±Ø§Ø¨Ø· Ø§Ù„ØªÙØ¹ÙŠÙ„ Ø§Ù„Ù…Ø­ØªÙˆÙŠ Ø¹Ù„Ù‰ Ø§Ù„ØªÙˆÙƒÙ†
     */
    public function sendVerificationEmail($toEmail, $toName, $activationUrl)
    {
        $htmlContent = '
            <div style="font-family: Tahoma, sans-serif; background-color: #f4f4f9; padding: 40px 0; direction: rtl;">
                <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                    <h2 style="color: #2563EB; text-align: center; font-size: 24px;">Ù…Ù†ØµØ© Code Shell</h2>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="color: #333; font-size: 16px;">Ù…Ø±Ø­Ø¨Ø§Ù‹ <strong>' . htmlspecialchars($toName) . '</strong>ØŒ</p>
                    <p style="color: #555; font-size: 15px; line-height: 1.7;">Ø´ÙƒØ±Ø§Ù‹ Ù„ØªØ³Ø¬ÙŠÙ„Ùƒ Ù…Ø¹Ù†Ø§ ÙÙŠ Ù…Ù†ØµØ© Code Shell. Ù„Ø¥ØªÙ…Ø§Ù… ØªÙØ¹ÙŠÙ„ Ø­Ø³Ø§Ø¨Ùƒ ÙˆØ§Ù„Ø¨Ø¯Ø¡ ÙÙŠ Ø§Ø³ØªÙƒØ´Ø§Ù Ø§Ù„ÙƒÙˆØ±Ø³Ø§Øª ÙˆØ§Ù„ØªØ¹Ù„Ù…ØŒ ÙŠØ±Ø¬Ù‰ Ø§Ù„Ø¶ØºØ· Ø¹Ù„Ù‰ Ø§Ù„Ø²Ø± Ø£Ø¯Ù†Ø§Ù‡:</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . $activationUrl . '" style="background-color: #2563EB; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-size: 16px; font-weight: bold; display: inline-block;">ØªØ£ÙƒÙŠØ¯ Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ âœ‰ï¸</a>
                    </div>
                    <p style="color: #777; font-size: 13px;">Ø¥Ø°Ø§ Ù„Ù… ÙŠØ¹Ù…Ù„ Ø§Ù„Ø²Ø±ØŒ ÙŠÙ…ÙƒÙ†Ùƒ Ù†Ø³Ø® Ø§Ù„Ø±Ø§Ø¨Ø· Ø§Ù„ØªØ§Ù„ÙŠ ÙˆÙ„ØµÙ‚Ù‡ ÙÙŠ Ø§Ù„Ù…ØªØµÙØ­:</p>
                    <p style="word-break: break-all; background: #f9f9f9; padding: 10px; border-radius: 5px; font-size: 12px; color: #2563EB;"><a href="' . $activationUrl . '">' . $activationUrl . '</a></p>
                    <p style="color: #d9534f; font-size: 13px; margin-top: 20px;">â± ØªÙ†Ø¨ÙŠÙ‡: Ù‡Ø°Ø§ Ø§Ù„Ø±Ø§Ø¨Ø· ØµØ§Ù„Ø­ Ù„Ù…Ø¯Ø© 24 Ø³Ø§Ø¹Ø© ÙÙ‚Ø·.</p>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="color: #999; font-size: 12px; text-align: center;">Ø¥Ø°Ø§ Ù„Ù… ØªØ·Ù„Ø¨ Ø¥Ù†Ø´Ø§Ø¡ Ù‡Ø°Ø§ Ø§Ù„Ø­Ø³Ø§Ø¨ØŒ ÙŠÙ…ÙƒÙ†Ùƒ ØªØ¬Ø§Ù‡Ù„ Ù‡Ø°Ù‡ Ø§Ù„Ø±Ø³Ø§Ù„Ø©. | ÙØ±ÙŠÙ‚ Ø¯Ø¹Ù… Code Shell</p>
                </div>
            </div>
        ';

        return $this->sendEmail($toEmail, $toName, 'âœ‰ï¸ ØªÙØ¹ÙŠÙ„ Ø­Ø³Ø§Ø¨Ùƒ Ø§Ù„Ø´Ø®ØµÙŠ ÙÙŠ Ù…Ù†ØµØ© Code Shell', $htmlContent);
    }

    /**
     * Ø¥Ø±Ø³Ø§Ù„ Ø±Ø³Ø§Ù„Ø© Ø§Ø³ØªØ¹Ø§Ø¯Ø© ÙˆØ¥Ø¹Ø§Ø¯Ø© ØªØ¹ÙŠÙŠÙ† ÙƒÙ„Ù…Ø© Ø§Ù„Ù…Ø±ÙˆØ±.
     * 
     * @param string $toEmail Ø¨Ø±ÙŠØ¯ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
     * @param string $toName Ø§Ø³Ù… Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
     * @param string $resetUrl Ø±Ø§Ø¨Ø· Ø§Ù„ØªØ¹ÙŠÙŠÙ† Ø§Ù„Ù…Ø­ØªÙˆÙŠ Ø¹Ù„Ù‰ Ø§Ù„ØªÙˆÙƒÙ†
     */
    public function sendPasswordResetEmail($toEmail, $toName, $resetUrl)
    {
        $htmlContent = '
            <div style="font-family: Tahoma, sans-serif; background-color: #f4f4f9; padding: 40px 0; direction: rtl;">
                <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                    <h2 style="color: #2563EB; text-align: center; font-size: 24px;">Ù…Ù†ØµØ© Code Shell</h2>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="color: #333; font-size: 16px;">Ù…Ø±Ø­Ø¨Ø§Ù‹ <strong>' . htmlspecialchars($toName) . '</strong>ØŒ</p>
                    <p style="color: #555; font-size: 15px; line-height: 1.7;">ØªÙ„Ù‚ÙŠÙ†Ø§ Ø·Ù„Ø¨Ø§Ù‹ Ù„Ø¥Ø¹Ø§Ø¯Ø© ØªØ¹ÙŠÙŠÙ† ÙƒÙ„Ù…Ø© Ø§Ù„Ù…Ø±ÙˆØ± Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø­Ø³Ø§Ø¨Ùƒ ÙÙŠ Ù…Ù†ØµØ© Code Shell. Ø§Ù†Ù‚Ø± Ø¹Ù„Ù‰ Ø§Ù„Ø²Ø± Ø£Ø¯Ù†Ø§Ù‡ Ù„Ø¥Ù†Ø´Ø§Ø¡ ÙƒÙ„Ù…Ø© Ù…Ø±ÙˆØ± Ø¬Ø¯ÙŠØ¯Ø©:</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . $resetUrl . '" style="background-color: #4F46E5; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-size: 16px; font-weight: bold; display: inline-block;">Ø¥Ø¹Ø§Ø¯Ø© ØªØ¹ÙŠÙŠÙ† ÙƒÙ„Ù…Ø© Ø§Ù„Ù…Ø±ÙˆØ± ðŸ”‘</a>
                    </div>
                    <p style="color: #777; font-size: 13px;">Ø¥Ø°Ø§ Ù„Ù… ÙŠØ¹Ù…Ù„ Ø§Ù„Ø²Ø±ØŒ ÙŠÙ…ÙƒÙ†Ùƒ Ù†Ø³Ø® Ø§Ù„Ø±Ø§Ø¨Ø· Ø§Ù„ØªØ§Ù„ÙŠ ÙˆÙ„ØµÙ‚Ù‡ ÙÙŠ Ø§Ù„Ù…ØªØµÙØ­:</p>
                    <p style="word-break: break-all; background: #f9f9f9; padding: 10px; border-radius: 5px; font-size: 12px; color: #4F46E5;"><a href="' . $resetUrl . '">' . $resetUrl . '</a></p>
                    <p style="color: #d9534f; font-size: 13px; margin-top: 20px;">â± ØªÙ†Ø¨ÙŠÙ‡: Ù‡Ø°Ø§ Ø§Ù„Ø±Ø§Ø¨Ø· ØµØ§Ù„Ø­ Ù„Ù…Ø¯Ø© 15 Ø¯Ù‚ÙŠÙ‚Ø© ÙÙ‚Ø·.</p>
                    <p style="color: #888; font-size: 13px;">Ø¥Ø°Ø§ Ù„Ù… ØªØ·Ù„Ø¨ Ø¥Ø¹Ø§Ø¯Ø© ØªØ¹ÙŠÙŠÙ† ÙƒÙ„Ù…Ø© Ø§Ù„Ù…Ø±ÙˆØ±ØŒ ÙŠÙ…ÙƒÙ†Ùƒ ØªØ¬Ø§Ù‡Ù„ Ù‡Ø°Ù‡ Ø§Ù„Ø±Ø³Ø§Ù„Ø© Ø¨Ø£Ù…Ø§Ù†.</p>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="color: #999; font-size: 12px; text-align: center;">ÙØ±ÙŠÙ‚ Ø¯Ø¹Ù… Code Shell</p>
                </div>
            </div>
        ';

        return $this->sendEmail($toEmail, $toName, 'ðŸ”‘ Ø¥Ø¹Ø§Ø¯Ø© ØªØ¹ÙŠÙŠÙ† ÙƒÙ„Ù…Ø© Ø§Ù„Ù…Ø±ÙˆØ± â€” Ù…Ù†ØµØ© Code Shell', $htmlContent);
    }
}
