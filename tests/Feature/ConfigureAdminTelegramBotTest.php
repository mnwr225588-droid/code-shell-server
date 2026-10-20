<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConfigureAdminTelegramBotTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_adds_admin_commands_to_the_chat_menu_button(): void
    {
        Config::set('services.telegram.bot_token', 'student-bot-token');
        Config::set('services.admin_telegram.bot_token', 'separate-admin-bot-token');
        Config::set('services.admin_telegram.chat_id', '987654321');
        Config::set('services.admin_telegram.secret_token', 'test_admin_webhook_secret_123456789');
        Config::set('services.admin_telegram.webhook_url', 'https://example.com/api/admin/telegram/webhook');

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->assertSame(0, Artisan::call('telegram:admin-secure-configure'));

        Http::assertSent(fn ($request) =>
            str_ends_with($request->url(), '/setChatMenuButton')
            && (int) $request['chat_id'] === 987654321
            && data_get($request->data(), 'menu_button.type') === 'commands'
        );
        Http::assertSent(fn ($request) =>
            str_ends_with($request->url(), '/setMyCommands')
            && collect($request['commands'])->pluck('command')->all() === ['start', 'menu', 'stats', 'groups']
        );
    }
}
