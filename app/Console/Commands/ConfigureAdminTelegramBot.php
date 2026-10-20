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

        $configurationErrors = [];
        if ($token === '') {
            $configurationErrors[] = 'ADMIN_TELEGRAM_BOT_TOKEN is missing.';
        } elseif (hash_equals((string) config('services.telegram.bot_token'), $token)) {
            $configurationErrors[] = 'ADMIN_TELEGRAM_BOT_TOKEN must be different from TELEGRAM_BOT_TOKEN.';
        }
        if (preg_match('/^\d+$/', $chatId) !== 1) {
            $configurationErrors[] = 'ADMIN_TELEGRAM_CHAT_ID must contain digits only. Run telegram:admin-chat-id to find it.';
        }
        if (preg_match('/^[A-Za-z0-9_-]{32,256}$/', $secret) !== 1) {
            $configurationErrors[] = 'ADMIN_TELEGRAM_BOT_SECRET must be 32-256 characters using only letters, digits, _ or -.';
        }

        if ($configurationErrors !== []) {
            foreach ($configurationErrors as $configurationError) {
                $this->error($configurationError);
            }
            $this->line('Set the values in the server environment, then run php artisan config:clear and retry.');
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
