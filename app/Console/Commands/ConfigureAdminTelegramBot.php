<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ConfigureAdminTelegramBot extends Command
{
    protected $signature = 'telegram:admin-secure-configure';

    protected $description = 'Configure the private administrator Telegram bot and secure webhook';

    public function handle(): int
    {
        $token = (string) config('services.admin_telegram.bot_token');
        $secret = (string) config('services.admin_telegram.secret_token');
        $chatId = (string) config('services.admin_telegram.chat_id');
        $webhookUrl = (string) (
            config('services.admin_telegram.webhook_url')
            ?: rtrim((string) config('app.url'), '/') . '/api/admin/telegram/webhook'
        );

        if (
            $token === ''
            || hash_equals((string) config('services.telegram.bot_token'), $token)
            || !preg_match('/^\d+$/', $chatId)
            || preg_match('/^[A-Za-z0-9_-]{32,256}$/', $secret) !== 1
        ) {
            $this->error('Configure a separate admin bot token, its private chat ID, and a 32-256 character webhook secret.');
            return self::FAILURE;
        }
        if (!str_starts_with($webhookUrl, 'https://')) {
            $this->error('The administrator Telegram webhook URL must use HTTPS.');
            return self::FAILURE;
        }

        $botApiUrl = "https://api.telegram.org/bot{$token}/";
        $requests = [
            'setMyDescription' => [
                'description' => 'لوحة إدارة Code Shell الخاصة. تعرض إحصائيات الطلاب والكورسات والمجموعات.',
            ],
            'setMyShortDescription' => [
                'short_description' => 'إدارة خاصة وآمنة لمنصة Code Shell.',
            ],
            'setMyCommands' => [
                'commands' => [
                    ['command' => 'start', 'description' => 'فتح لوحة الإدارة'],
                    ['command' => 'menu', 'description' => 'عرض قائمة الإدارة'],
                    ['command' => 'stats', 'description' => 'عرض إحصائيات المنصة'],
                    ['command' => 'groups', 'description' => 'عرض الكورسات ومجموعاتها'],
                ],
            ],
            'setChatMenuButton' => [
                'chat_id' => (int) $chatId,
                'menu_button' => ['type' => 'commands'],
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
                    ->connectTimeout(3)
                    ->post($botApiUrl . $method, $payload);
            } catch (\Throwable) {
                $this->error("Telegram API request failed for {$method}.");
                return self::FAILURE;
            }

            if (!$response->successful() || $response->json('ok') !== true) {
                $this->error("Telegram API rejected {$method}.");
                return self::FAILURE;
            }
        }

        $this->info('The private administrator bot, commands, and secure webhook are configured.');
        return self::SUCCESS;
    }
}
