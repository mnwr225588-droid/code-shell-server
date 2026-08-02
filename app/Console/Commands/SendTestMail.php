<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestMail extends Command
{
    protected $signature = 'mail:test
        {--to= : البريد الإلكتروني المستلم (إلزامي)}
        {--subject= : عنوان الرسالة (اختياري)}';

    protected $description = 'إرسال بريد اختباري عبر إعدادات SMTP الحالية للتحقق من أن السيرفر جاهز لإرسال البريد';

    public function handle(): int
    {
        $to = $this->option('to') ?? '';

        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('يرجى تحديد بريد مستلم صحيح: php artisan mail:test --to=example@example.com');
            return self::FAILURE;
        }

        $subject = $this->option('subject')
            ?? 'Test email from Code Shell (' . now()->format('Y-m-d H:i:s') . ')';

        $from = config('mail.from.address');
        $mailer = config('mail.default');

        $this->info("إرسال بريد اختباري إلى: {$to}");
        $this->info("المرسل: {$from}");
        $this->info("المحرك الحالي (MAIL_MAILER): {$mailer}");
        $this->line('');

        try {
            Mail::raw(
                "This is a test email sent from the Code Shell server.\n"
                . "If you received this message, the SMTP configuration is working correctly.\n"
                . 'Sent at: ' . now()->format('Y-m-d H:i:s')
                . "\nMailer: {$mailer}\nFrom: {$from}",
                function ($message) use ($to, $subject, $from) {
                    $message->to($to)->subject($subject);
                }
            );

            $this->info('تم إرسال البريد بنجاح ✅');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('فشل إرسال البريد: ' . $e->getMessage());
            $this->error('تأكد من بيانات SMTP داخل ملف .env أو متغيرات البيئة على Railway.');
            return self::FAILURE;
        }
    }
}
