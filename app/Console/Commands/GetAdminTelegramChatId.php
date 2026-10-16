<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GetAdminTelegramChatId extends Command
{
    protected $signature = 'telegram:admin-chat-id';
    protected $description = 'Read the private chat ID that started a conversation with the admin-only Telegram bot';

    public function handle(): int
    {
        $token = (string) config('services.admin_telegram.bot_token');
        if ($token === '') {
            $this->error('Set ADMIN_TELEGRAM_BOT_TOKEN in the server environment first.');
            return self::FAILURE;
        }
        if (hash_equals((string) config('services.telegram.bot_token'), $token)) {
            $this->error('The admin bot must have a different token from the student bot.');
            return self::FAILURE;
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(3)
                ->get("https://api.telegram.org/bot{$token}/getUpdates", [
                    'limit' => 100,
                    'allowed_updates' => json_encode(['message']),
                ]);
        } catch (\Throwable $exception) {
            $this->error('Could not connect to Telegram to read admin bot updates.');
            return self::FAILURE;
        }

        if (!$response->successful() || $response->json('ok') !== true) {
            $this->error('Telegram rejected getUpdates. Make sure this new admin bot does not have a webhook configured.');
            return self::FAILURE;
        }

        $privateMessages = collect($response->json('result', []))
            ->map(fn (array $update) => $update['message'] ?? null)
            ->filter(fn ($message) =>
                is_array($message)
                && data_get($message, 'chat.type') === 'private'
                && preg_match('/^\/start(?:@\w+)?(?:\s|$)/', (string) data_get($message, 'text', '')) === 1
            );

        $chats = $privateMessages
            ->map(fn (array $message) => [
                'id' => data_get($message, 'chat.id'),
                'name' => trim((string) data_get($message, 'from.first_name', '') . ' ' . (string) data_get($message, 'from.last_name', '')),
                'username' => data_get($message, 'from.username'),
            ])
            ->unique('id')
            ->values();

        if ($chats->isEmpty()) {
            $this->warn('No private /start message found. Open the admin bot in Telegram and press Start, then run this command again.');
            return self::FAILURE;
        }

        if ($chats->count() > 1) {
            $this->warn('Multiple Telegram users have started this bot. Verify the intended admin account and use its private chat ID:');
            $this->table(['Private chat ID', 'Name', 'Username'], $chats->map(fn (array $chat) => [
                $chat['id'],
                $chat['name'] ?: '—',
                $chat['username'] ? '@' . $chat['username'] : '—',
            ])->all());
            return self::FAILURE;
        }

        $this->info('Admin private chat ID: ' . $chats->first()['id']);
        $this->line('Set ADMIN_TELEGRAM_CHAT_ID to this value in the production environment, then clear the Laravel config cache.');
        return self::SUCCESS;
    }
}
