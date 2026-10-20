<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseGroup;
use App\Services\AdminTelegramBotService;
use App\Services\CourseGroupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminTelegramWebhookController extends Controller
{
    public function __construct(private AdminTelegramBotService $telegramBot) {}

    public function handle(Request $request)
    {
        $secret = (string) config('services.admin_telegram.secret_token');
        if ($secret === '' || preg_match('/^[A-Za-z0-9_-]{32,256}$/', $secret) !== 1) {
            Log::critical('Admin Telegram webhook secret is missing or invalid.');
            return response()->json(['status' => 'error'], 503);
        }

        $providedSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        if (!hash_equals($secret, $providedSecret)) {
            return response()->json(['status' => 'error'], 403);
        }

        if (!$this->telegramBot->isConfigured()) {
            Log::critical('Admin Telegram webhook received an update without a configured admin bot.');
            return response()->json(['status' => 'error'], 503);
        }

        $message = $request->input('message');
        if (is_array($message)) {
            $chat = $message['chat'] ?? [];
            $senderId = (string) data_get($message, 'from.id', '');
            if (($chat['type'] ?? null) === 'private' && $this->isAuthorizedChat($chat['id'] ?? null, $senderId)) {
                $this->handleMessage((string) ($message['text'] ?? ''));
            }
        }

        $callback = $request->input('callback_query');
        if (is_array($callback)) {
            $chat = data_get($callback, 'message.chat', []);
            $senderId = (string) data_get($callback, 'from.id', '');
            $callbackId = (string) ($callback['id'] ?? '');
            if (($chat['type'] ?? null) !== 'private' || !$this->isAuthorizedChat($chat['id'] ?? null, $senderId)) {
                if ($callbackId !== '') {
                    $this->telegramBot->answerCallback($callbackId, 'هذا الزر مخصص لأدمن المنصة فقط.', true);
                }
            } elseif ($callbackId !== '') {
                $this->telegramBot->answerCallback($callbackId);
                $this->handleCallback((string) data_get($callback, 'data', ''));
            }
        }

        return response()->json(['status' => 'success']);
    }

    private function isAuthorizedChat(mixed $chatId, string $senderId): bool
    {
        $adminChatId = (string) config('services.admin_telegram.chat_id');

        return $adminChatId !== ''
            && (string) $chatId === $adminChatId
            && $senderId === $adminChatId;
    }

    private function handleMessage(string $text): void
    {
        $shortcut = trim($text);
        $command = strtolower(trim(strtok($text, " \n\t") ?: ''));
        match (true) {
            $shortcut === AdminTelegramBotService::SHORTCUT_MENU,
            in_array($command, ['/start', '/menu', '/help'], true) => $this->telegramBot->sendAdminMenu(),
            $shortcut === AdminTelegramBotService::SHORTCUT_STATS,
            $command === '/stats' => $this->telegramBot->sendStatistics(),
            $shortcut === AdminTelegramBotService::SHORTCUT_GROUPS,
            $command === '/groups' => $this->telegramBot->sendCourseList(),
            default => $this->telegramBot->sendAdminMenu(),
        };
    }

    private function handleCallback(string $data): void
    {
        if ($data === 'admin:menu') {
            $this->telegramBot->sendAdminMenu();
            return;
        }
        if ($data === 'admin:stats') {
            $this->telegramBot->sendStatistics();
            return;
        }
        if (preg_match('/^admin:courses:(\d+)$/', $data, $matches) === 1) {
            $this->telegramBot->sendCourseList((int) $matches[1]);
            return;
        }
        if (preg_match('/^admin:course:(\d+):(\d+)$/', $data, $matches) === 1) {
            $this->telegramBot->sendCourseGroups((int) $matches[1], (int) $matches[2]);
            return;
        }
        if (preg_match('/^admin:activate:(\d+)$/', $data, $matches) === 1) {
            $group = CourseGroup::query()->with('course')->find((int) $matches[1]);
            $message = $this->telegramBot->activateFullGroup((int) $matches[1]);
            $keyboard = $group
                ? ['inline_keyboard' => [[
                    ['text' => '👥 العودة لمجموعات الكورس', 'callback_data' => 'admin:course:' . $group->course_id . ':0'],
                ]]]
                : ['inline_keyboard' => [[
                    ['text' => '👥 عرض المجموعات', 'callback_data' => 'admin:courses:0'],
                ]]];
            $this->telegramBot->sendMessage($message, $keyboard);
            return;
        }

        $this->telegramBot->sendAdminMenu();
    }
}
