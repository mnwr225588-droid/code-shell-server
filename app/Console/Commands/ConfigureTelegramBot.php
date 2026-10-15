<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ConfigureTelegramBot extends Command
{
    protected $signature = 'telegram:secure-configure';
    protected $description = 'Configure the official Telegram bot profile and secure webhook';

    public function handle(): int
    {
        $token = (string) config('services.telegram.bot_token');
        $secret = (string) config('services.telegram.secret_token');
        $webhookUrl = (string) (
            config('services.telegram.webhook_url')
            ?: rtrim((string) config('app.url'), '/') . '/api/telegram/webhook'
        );

        if ($token === '' || !preg_match('/^[A-Za-z0-9_-]{1,256}$/', $secret)) {
            $this->error('Configure TELEGRAM_BOT_TOKEN and a valid TELEGRAM_BOT_SECRET first.');
            return self::FAILURE;
        }

        if (!str_starts_with($webhookUrl, 'https://')) {
            $this->error('The Telegram webhook URL must use HTTPS.');
            return self::FAILURE;
        }

        $botApiUrl = "https://api.telegram.org/bot{$token}/";
        $requests = [
            'setMyDescription' => [
                'description' => 'بوت Code Shell الرسمي. يرسل ردود ربط الحساب وإشعارات المنصة وشحن المحفظة فقط.',
            ],
            'setMyShortDescription' => [
                'short_description' => 'إشعارات وردود رسمية من منصة Code Shell فقط.',
            ],
            'setMyCommands' => [
                'commands' => [
                    ['command' => 'start', 'description' => 'ربط حسابك وعرض حالة الحساب'],
                ],
            ],
            'setWebhook' => [
                'url' => $webhookUrl,
                'secret_token' => $secret,
                'allowed_updates' => ['message', 'callback_query'],
            ],
        ];

        foreach ($requests as $method => $payload) {
            try {
                $response = Http::asJson()
                    ->timeout(10)
                    ->post($botApiUrl . $method, $payload);
            } catch (\Throwable $e) {
                $this->error("Telegram API request failed for {$method}.");
                return self::FAILURE;
            }

            if (!$response->successful() || $response->json('ok') !== true) {
                $this->error("Telegram API rejected {$method}.");
                return self::FAILURE;
            }
        }

        $this->info('Telegram profile, commands, and secret webhook are configured.');
        return self::SUCCESS;
    }
}
