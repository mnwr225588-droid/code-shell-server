<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BrevoMailService;

class TestBrevoEmail extends Command
{
    // اسم الأمر في التيرمينال مع خيار تحديد البريد المستلم
    protected $signature = 'brevo:test {--to= : البريد الإلكتروني المستلم}';
    
    // وصف الأمر
    protected $description = 'اختبار إرسال بريد إلكتروني HTML عبر Brevo API';

    public function handle(BrevoMailService $brevoService)
    {
        $to = $this->option('to');

        if (!$to) {
            $this->error('خطأ: يرجى تحديد البريد المستلم هكذا: php artisan brevo:test --to=example@gmail.com');
            return 1;
        }

        $this->info("جاري إرسال رسالة HTML تجريبية إلى: $to ...");

        // محتوى الـ HTML التجريبي
        $htmlContent = '
            <div style="font-family: Tahoma, sans-serif; text-align: center; padding: 30px; background: #f4f4f9; border-radius: 8px;">
                <h2 style="color: #28a745;">نجاح اختبار Brevo API!</h2>
                <p style="color: #555;">هذه رسالة HTML تجريبية تم إرسالها بنجاح عبر خدمة Brevo API بدون مشاكل SMTP.</p>
                <hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">
                <p style="font-size: 12px; color: #999;">منصة Code Shell</p>
            </div>
        ';

        // استدعاء خدمة الإرسال
        $result = $brevoService->sendEmail(
            $to, 
            'مستخدم تجريبي', 
            'اختبار ناجح لـ Brevo API', 
            $htmlContent
        );

        // طباعة النتيجة في التيرمينال
        if ($result['success']) {
            $this->info('✔ ' . $result['message']);
            return 0;
        } else {
            $this->error('✖ ' . $result['message']);
            return 1;
        }
    }
}