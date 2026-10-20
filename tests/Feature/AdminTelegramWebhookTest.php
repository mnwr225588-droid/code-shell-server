<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\CourseSubscription;
use App\Models\User;
use App\Services\AdminTelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminTelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_CHAT_ID = '987654321';
    private const WEBHOOK_SECRET = 'test_admin_webhook_secret_123456789';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.telegram.bot_token', 'student-bot-token');
        Config::set('services.admin_telegram.bot_token', 'separate-admin-bot-token');
        Config::set('services.admin_telegram.chat_id', self::ADMIN_CHAT_ID);
        Config::set('services.admin_telegram.secret_token', self::WEBHOOK_SECRET);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
        ]);
    }

    public function test_admin_webhook_rejects_requests_without_telegram_secret(): void
    {
        $this->postJson('/api/admin/telegram/webhook', [
            'message' => $this->adminMessage('/start'),
        ])->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_admin_bot_ignores_private_messages_from_other_chat_ids(): void
    {
        $this->adminWebhook()->postJson('/api/admin/telegram/webhook', [
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 555555],
                'chat' => ['id' => 555555, 'type' => 'private'],
                'text' => '/start',
            ],
        ])->assertOk();

        Http::assertNothingSent();
    }

    public function test_start_command_shows_the_admin_menu_with_statistics_and_group_actions(): void
    {
        $this->adminWebhook()->postJson('/api/admin/telegram/webhook', [
            'message' => $this->adminMessage('/start'),
        ])->assertOk();

        Http::assertSent(fn ($request) =>
            str_ends_with($request->url(), '/sendMessage')
            && data_get($request->data(), 'reply_markup.inline_keyboard.0.0.callback_data') === 'admin:stats'
            && data_get($request->data(), 'reply_markup.inline_keyboard.0.1.callback_data') === 'admin:courses:0'
        );
    }

    public function test_statistics_keyboard_shortcut_opens_live_platform_statistics(): void
    {
        $this->adminWebhook()->postJson('/api/admin/telegram/webhook', [
            'message' => $this->adminMessage(AdminTelegramBotService::SHORTCUT_STATS),
        ])->assertOk();

        Http::assertSent(fn ($request) =>
            str_ends_with($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], 'إحصائيات منصة Code Shell')
        );
    }

    public function test_groups_keyboard_shortcut_opens_course_list(): void
    {
        $this->courseWithGroup('Shortcut Course');

        $this->adminWebhook()->postJson('/api/admin/telegram/webhook', [
            'message' => $this->adminMessage(AdminTelegramBotService::SHORTCUT_GROUPS),
        ])->assertOk();

        Http::assertSent(fn ($request) =>
            str_ends_with($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], 'الكورسات والمجموعات')
            && data_get($request->data(), 'reply_markup.inline_keyboard.0.0.callback_data', '')
                === 'admin:course:' . Course::firstOrFail()->id . ':0'
        );
    }

    public function test_statistics_callback_returns_live_platform_counts(): void
    {
        [$course] = $this->courseWithGroup('Statistics Course');
        $this->adminWebhook()->postJson('/api/admin/telegram/webhook', [
            'callback_query' => $this->adminCallback('admin:stats'),
        ])->assertOk();

        Http::assertSent(fn ($request) =>
            str_ends_with($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], 'إحصائيات منصة Code Shell')
            && str_contains((string) $request['text'], 'الكورسات المنشورة')
            && str_contains((string) $request['text'], 'المجموعات')
        );
    }

    public function test_course_callback_displays_group_list_and_member_capacity(): void
    {
        [$course, $group] = $this->courseWithGroup('Group List Course');
        $student = $this->student('group-list@example.com', 'Group List Student');
        CourseSubscription::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'group_id' => $group->id,
            'subscription_status' => CourseSubscription::STATUS_ACTIVE,
        ]);

        $this->adminWebhook()->postJson('/api/admin/telegram/webhook', [
            'callback_query' => $this->adminCallback('admin:course:' . $course->id . ':0'),
        ])->assertOk();

        Http::assertSent(fn ($request) =>
            str_ends_with($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], 'Group List Course')
            && str_contains((string) $request['text'], 'المجموعة الأولى')
            && str_contains((string) $request['text'], '1/2 طالب')
        );
    }

    public function test_activation_callback_activates_only_a_full_group(): void
    {
        [$course, $group] = $this->courseWithGroup('Activation Course', 2);
        $this->addStudentSubscription($course, $group, 'first-group-member@example.com', 'First Member');
        $this->addStudentSubscription($course, $group, 'second-group-member@example.com', 'Second Member');

        $this->adminWebhook()->postJson('/api/admin/telegram/webhook', [
            'callback_query' => $this->adminCallback('admin:activate:' . $group->id),
        ])->assertOk();

        $this->assertDatabaseHas('course_groups', [
            'id' => $group->id,
            'status' => 'active',
        ]);
        Http::assertSent(fn ($request) =>
            str_ends_with($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], 'تم تفعيل مجموعة')
        );
    }

    public function test_activation_callback_cannot_activate_a_group_before_it_is_full(): void
    {
        [$course, $group] = $this->courseWithGroup('Not Full Course', 2);
        $this->addStudentSubscription($course, $group, 'only-member@example.com', 'Only Member');

        $this->adminWebhook()->postJson('/api/admin/telegram/webhook', [
            'callback_query' => $this->adminCallback('admin:activate:' . $group->id),
        ])->assertOk();

        $this->assertDatabaseHas('course_groups', [
            'id' => $group->id,
            'status' => 'open_for_registration',
        ]);
        Http::assertSent(fn ($request) =>
            str_ends_with($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], 'لم تكتمل المجموعة بعد')
        );
    }

    private function adminWebhook()
    {
        return $this->withHeader('X-Telegram-Bot-Api-Secret-Token', self::WEBHOOK_SECRET);
    }

    private function adminMessage(string $text): array
    {
        return [
            'message_id' => 1,
            'from' => ['id' => (int) self::ADMIN_CHAT_ID],
            'chat' => ['id' => (int) self::ADMIN_CHAT_ID, 'type' => 'private'],
            'text' => $text,
        ];
    }

    private function adminCallback(string $data): array
    {
        return [
            'id' => 'admin-callback-id',
            'from' => ['id' => (int) self::ADMIN_CHAT_ID],
            'message' => [
                'message_id' => 2,
                'chat' => ['id' => (int) self::ADMIN_CHAT_ID, 'type' => 'private'],
            ],
            'data' => $data,
        ];
    }

    private function courseWithGroup(string $title, int $capacity = 2): array
    {
        $category = Category::create(['name' => 'Telegram Test Category']);
        $course = Course::create([
            'category_id' => $category->id,
            'title' => $title,
            'is_free' => true,
            'is_active' => true,
        ]);
        $group = CourseGroup::create([
            'course_id' => $course->id,
            'name' => 'المجموعة الأولى',
            'capacity' => $capacity,
            'is_auto_create' => false,
            'status' => 'open_for_registration',
        ]);

        return [$course, $group];
    }

    private function addStudentSubscription(Course $course, CourseGroup $group, string $email, string $name): User
    {
        $student = $this->student($email, $name);
        CourseSubscription::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'group_id' => $group->id,
            'subscription_status' => CourseSubscription::STATUS_ACTIVE,
        ]);

        return $student;
    }

    private function student(string $email, string $name): User
    {
        return User::create([
            'name' => $name,
            'first_name' => $name,
            'middle_name' => 'Telegram',
            'last_name' => 'Test',
            'email' => $email,
            'password' => Hash::make('password123'),
            'country' => 'EG',
            'birth_date' => '2000-01-01',
            'phone' => '01000000000',
        ]);
    }
}
