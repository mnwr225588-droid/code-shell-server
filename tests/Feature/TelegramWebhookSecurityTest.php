<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TelegramWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.telegram.bot_token', 'test-bot-token');
        Config::set('services.telegram.secret_token', 'test_webhook_secret_123');
    }

    public function test_webhook_rejects_requests_without_telegram_secret(): void
    {
        Http::fake();

        $response = $this->postJson('/api/telegram/webhook', [
            'message' => [
                'chat' => ['id' => 12345, 'type' => 'private'],
                'text' => '/start',
            ],
        ]);

        $response->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_authenticated_start_keeps_the_platform_account_reply(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
        ]);

        $response = $this->withHeader(
            'X-Telegram-Bot-Api-Secret-Token',
            'test_webhook_secret_123',
        )->postJson('/api/telegram/webhook', [
            'message' => [
                'chat' => ['id' => 12345, 'type' => 'private'],
                'text' => '/start',
            ],
        ]);

        $response->assertOk()->assertJson(['status' => 'success']);
        Http::assertSent(fn ($request) =>
            str_ends_with($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], 'Code Shell Platform')
        );
    }

    public function test_webhook_does_not_reply_to_group_messages(): void
    {
        Http::fake();

        $response = $this->withHeader(
            'X-Telegram-Bot-Api-Secret-Token',
            'test_webhook_secret_123',
        )->postJson('/api/telegram/webhook', [
            'message' => [
                'chat' => ['id' => -12345, 'type' => 'group'],
                'text' => '/start',
            ],
        ]);

        $response->assertOk()->assertJson(['status' => 'success']);
        Http::assertNothingSent();
    }

    public function test_only_known_platform_events_are_forwarded_automatically(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
        ]);

        $user = User::create([
            'name' => 'Telegram Test Student',
            'first_name' => 'Telegram',
            'middle_name' => 'Test',
            'last_name' => 'Student',
            'email' => 'telegram-test@example.com',
            'password' => Hash::make('password123'),
            'country' => 'EGP',
            'birth_date' => '2000-01-01',
            'phone' => '01000000000',
            'telegram_chat_id' => '12345',
        ]);

        PushNotificationService::sendToUsers(
            [$user],
            'Unapproved content',
            'This general notification must not reach Telegram.',
            type: 'general',
        );
        Http::assertNothingSent();

        PushNotificationService::sendToUsers(
            [$user],
            'Course update',
            'This platform event may reach Telegram.',
            type: 'course',
        );
        Http::assertSentCount(1);
    }
}
