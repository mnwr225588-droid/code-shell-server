<?php

namespace App\Console\Commands;

use App\Services\AdminTelegramBotService;
use Illuminate\Console\Command;

class TestAdminTelegramBot extends Command
{
    protected $signature = 'telegram:admin-test';
    protected $description = 'Send a test message through the independent admin Telegram bot';

    public function handle(AdminTelegramBotService $telegram): int
    {
        $token = (string) config('services.admin_telegram.bot_token');
        $chatId = (string) config('services.admin_telegram.chat_id');
        if ($token === '' || $chatId === '') {
            $this->error('Set ADMIN_TELEGRAM_BOT_TOKEN and ADMIN_TELEGRAM_CHAT_ID in the server environment first.');
            return self::FAILURE;
        }

        if ($telegram->sendTestMessage()) {
            $this->info('Test message sent to the configured admin Telegram chat.');
            return self::SUCCESS;
        }

        $this->error('Test message was not delivered. Verify ADMIN_TELEGRAM_BOT_TOKEN, ADMIN_TELEGRAM_CHAT_ID, and the server log.');
        return self::FAILURE;
    }
}
