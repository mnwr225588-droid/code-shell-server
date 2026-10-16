<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
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
