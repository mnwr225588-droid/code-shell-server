<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\CourseSubscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\AdminTelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTelegramBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.telegram.bot_token', 'student-bot-token');
        Config::set('services.admin_telegram.bot_token', 'separate-admin-bot-token');
        Config::set('services.admin_telegram.chat_id', '987654321');

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
        ]);
    }

    public function test_subscription_alert_uses_the_separate_admin_bot_and_includes_operation_details(): void
    {
        [$user, $course] = $this->studentAndCourse();
        $subscription = new CourseSubscription([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'subscription_status' => CourseSubscription::STATUS_ACTIVE,
            'payment_status' => CourseSubscription::PAYMENT_PAID,
            'amount' => 250,
            'currency_code' => 'EGP',
            'payment_gateway' => 'wallet',
            'paid_at' => now(),
        ]);
        $subscription->setRelation('user', $user);
        $subscription->setRelation('course', $course);
        $subscription->setRelation('plan', null);
        $subscription->setRelation('group', null);

        $sent = app(AdminTelegramBotService::class)->notifySubscription($subscription);

        $this->assertTrue($sent);
        Http::assertSent(fn ($request) =>
            str_contains($request->url(), '/botseparate-admin-bot-token/sendMessage')
            && (string) $request['chat_id'] === '987654321'
            && str_contains($request['text'], 'اشتراك جديد تم تفعيله')
            && str_contains($request['text'], 'student@example.com')
            && str_contains($request['text'], '01000000000')
            && str_contains($request['text'], 'Test Course')
            && str_contains($request['text'], '250.00 EGP')
        );
    }

    public function test_subscription_alert_includes_the_members_group_position_and_current_capacity(): void
    {
        [$firstUser, $course] = $this->studentAndCourse();
        $secondUser = User::create([
            'name' => 'Second Student',
            'first_name' => 'Second',
            'middle_name' => 'Test',
            'last_name' => 'Student',
            'email' => 'second@example.com',
            'password' => Hash::make('password123'),
            'country' => 'EG',
            'birth_date' => '2000-01-01',
            'phone' => '01000000001',
        ]);
        $group = CourseGroup::create([
            'course_id' => $course->id,
            'name' => 'المجموعة الأولى',
            'capacity' => 3,
            'status' => 'open_for_registration',
        ]);
        CourseSubscription::create([
            'user_id' => $firstUser->id,
            'course_id' => $course->id,
            'group_id' => $group->id,
            'subscription_status' => CourseSubscription::STATUS_ACTIVE,
        ]);
        $subscription = CourseSubscription::create([
            'user_id' => $secondUser->id,
            'course_id' => $course->id,
            'group_id' => $group->id,
            'subscription_status' => CourseSubscription::STATUS_ACTIVE,
        ]);

        $this->assertTrue(app(AdminTelegramBotService::class)->notifySubscription($subscription));

        Http::assertSent(fn ($request) =>
            str_contains((string) $request['text'], 'ترتيب العضو في المجموعة:</b> 2')
            && str_contains((string) $request['text'], 'عدد المجموعة الحالي:</b> 2/3 طالب')
        );
    }

    public function test_full_group_alert_includes_all_student_names_and_emails_and_can_only_be_sent_once(): void
    {
        [$firstUser, $course] = $this->studentAndCourse();
        $secondUser = User::create([
            'name' => 'Second Student',
            'first_name' => 'Second',
            'middle_name' => 'Test',
            'last_name' => 'Student',
            'email' => 'second@example.com',
            'password' => Hash::make('password123'),
            'country' => 'EG',
            'birth_date' => '2000-01-01',
            'phone' => '01000000001',
        ]);
        $group = CourseGroup::create([
            'course_id' => $course->id,
            'name' => 'المجموعة المكتملة',
            'capacity' => 2,
            'status' => 'open_for_registration',
        ]);
        CourseSubscription::create([
            'user_id' => $firstUser->id,
            'course_id' => $course->id,
            'group_id' => $group->id,
            'subscription_status' => CourseSubscription::STATUS_ACTIVE,
        ]);
        CourseSubscription::create([
            'user_id' => $secondUser->id,
            'course_id' => $course->id,
            'group_id' => $group->id,
            'subscription_status' => CourseSubscription::STATUS_ACTIVE,
        ]);
        $service = app(AdminTelegramBotService::class);
        $this->assertFalse($service->notifyCompletedGroupOnce($group));
        $this->assertNotNull(CourseGroup::find($group->id)?->admin_telegram_full_alerted_at);

        $fullGroupAlerts = Http::recorded(fn ($request) =>
            str_contains((string) $request['text'], 'اكتملت المجموعة')
        );
        $this->assertCount(1, $fullGroupAlerts);
        $fullGroupAlert = $fullGroupAlerts->first()[0];
        $this->assertStringContainsString('Test Example Student', (string) $fullGroupAlert['text']);
        $this->assertStringContainsString('student@example.com', (string) $fullGroupAlert['text']);
        $this->assertStringContainsString('Second Test Student', (string) $fullGroupAlert['text']);
        $this->assertStringContainsString('second@example.com', (string) $fullGroupAlert['text']);
        $this->assertSame(
            'admin:activate:' . $group->id,
            data_get($fullGroupAlert->data(), 'reply_markup.inline_keyboard.0.0.callback_data')
        );
    }

    public function test_admin_menu_has_statistics_and_group_buttons(): void
    {
        $this->assertTrue(app(AdminTelegramBotService::class)->sendAdminMenu());

        Http::assertSent(fn ($request) =>
            str_contains((string) $request['text'], 'لوحة إدارة Code Shell')
            && data_get($request->data(), 'reply_markup.inline_keyboard.0.0.callback_data') === 'admin:stats'
            && data_get($request->data(), 'reply_markup.inline_keyboard.0.1.callback_data') === 'admin:courses:0'
            && data_get($request->data(), 'reply_markup.keyboard') === null
        );
    }

    public function test_statistics_include_legacy_active_subscriptions_and_count_unique_students(): void
    {
        [$student, $course] = $this->studentAndCourse();
        $otherStudent = User::create([
            'name' => 'Other Student',
            'first_name' => 'Other',
            'middle_name' => 'Test',
            'last_name' => 'Student',
            'email' => 'other@example.com',
            'password' => Hash::make('password123'),
            'country' => 'EG',
            'birth_date' => '2000-01-01',
            'phone' => '01000000001',
        ]);
        CourseSubscription::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'subscription_status' => null,
        ]);
        CourseSubscription::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'subscription_status' => CourseSubscription::STATUS_ACTIVE,
        ]);
        CourseSubscription::create([
            'user_id' => $otherStudent->id,
            'course_id' => $course->id,
            'subscription_status' => CourseSubscription::STATUS_PENDING,
        ]);

        $this->assertTrue(app(AdminTelegramBotService::class)->sendStatistics());

        Http::assertSent(fn ($request) =>
            str_contains((string) $request['text'], 'حسابات الطلاب المسجلة: <b>2</b>')
            && str_contains((string) $request['text'], 'الطلاب ذوو الاشتراك النشط: <b>1</b>')
            && str_contains((string) $request['text'], 'الاشتراكات النشطة أو القديمة: <b>2</b>')
        );
    }

    public function test_wallet_alert_contains_balance_before_after_and_reason(): void
    {
        [$user] = $this->studentAndCourse();
        $movement = new WalletTransaction([
            'user_id' => $user->id,
            'type' => 'course_purchase',
            'amount' => 75,
            'balance_after' => 125,
            'reference_id' => 'course-42',
            'description' => 'شراء كورس Test Course',
        ]);
        $movement->created_at = now();
        $movement->setRelation('user', $user);

        $sent = app(AdminTelegramBotService::class)->notifyWalletMovement($movement);

        $this->assertTrue($sent);
        Http::assertSent(fn ($request) =>
            str_contains($request->url(), '/botseparate-admin-bot-token/sendMessage')
            && str_contains($request['text'], 'خصم من المحفظة')
            && str_contains($request['text'], '200.00 EGP')
            && str_contains($request['text'], '125.00 EGP')
            && str_contains($request['text'], 'شراء كورس Test Course')
        );
    }

    public function test_admin_bot_refuses_to_reuse_the_student_bot_token(): void
    {
        Config::set('services.admin_telegram.bot_token', 'student-bot-token');

        $sent = app(AdminTelegramBotService::class)->sendTestMessage();

        $this->assertFalse($sent);
        Http::assertNothingSent();
    }

    public function test_pending_payment_alert_is_sent_only_once_for_a_transaction(): void
    {
        [$user, $course] = $this->studentAndCourse();
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'amount' => 125,
            'currency_code' => 'EGP',
            'payment_gateway' => 'easykash',
            'status' => Transaction::STATUS_PENDING,
            'payload' => ['plan_id' => null],
        ]);

        $service = app(AdminTelegramBotService::class);
        $firstResult = $service->notifyPaymentPendingOnce($transaction, 'بانتظار تأكيد البوابة');
        $secondResult = $service->notifyPaymentPendingOnce($transaction, 'تكرار فحص الحالة');

        $this->assertTrue($firstResult);
        $this->assertFalse($secondResult);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) =>
            str_contains($request['text'], 'الدفع قيد التأكيد')
            && str_contains($request['text'], 'Test Course')
            && str_contains($request['text'], '125.00 EGP')
            && str_contains($request['text'], 'بانتظار تأكيد البوابة')
        );
    }

    private function studentAndCourse(): array
    {
        $user = User::create([
            'name' => 'Test Student',
            'first_name' => 'Test',
            'middle_name' => 'Example',
            'last_name' => 'Student',
            'email' => 'student@example.com',
            'password' => Hash::make('password123'),
            'country' => 'EGP',
            'birth_date' => '2000-01-01',
            'phone' => '01000000000',
        ]);
        $category = Category::create(['name' => 'Test Category']);
        $course = Course::create([
            'category_id' => $category->id,
            'title' => 'Test Course',
            'is_free' => false,
            'price' => 250,
            'is_active' => true,
        ]);

        return [$user, $course];
    }
}
