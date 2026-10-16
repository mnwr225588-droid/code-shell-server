<?php

/**
 * ====================================================
 * اسم الملف: EasyKashPaymentTest.php
 * المسار: tests/Feature/EasyKashPaymentTest.php
 * 
 * الوصف والمهمة الرئيسية:
 * هذا الملف يحتوي على مجموعة الاختبارات التلقائية (Automated Feature Tests)
 * لنظام الدفع والاشتراكات الخاص ببوابة EasyKash وحماية محتوى الكورسات.
 * 
 * سيناريوهات الاختبار:
 * 1. test_free_course_auto_subscribes_user_without_easykash_payment: تفعيل الكورس المجاني تلقائياً.
 * 2. test_paid_course_initiation_uses_server_db_price_ignoring_client: حساب السعر سيرفرياً من DB وتجاهل مدخلات الطالب.
 * 3. test_unsubscribed_user_receiving_403_on_course_levels: رفض دخول غير المشتركين بحالة 403 Forbidden.
 * 4. test_subscribed_user_granted_access_to_levels: السماح للمشترك الفعال بالوصول بحالة 200 OK.
 * 5. test_easykash_get_callback_activates_subscription: تفعيل الاشتراك بعد Callback صحيح مع التحقق من توقيع HMAC.
 * 6. test_duplicate_callback_does_not_duplicate_subscription: ضمان عدم تكرار التفعيل عند وصول الـ Callback مكرراً (Idempotency).
 * ====================================================
 */

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CoursePlan;
use App\Models\CourseSubscription;
use App\Models\Level;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EasyKashPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;

    /**
     * الإعداد الأولي قبل تشغيل كل اختبار
     */
    protected function setUp(): void
    {
        parent::setUp();

        // إنشاء قسم تجريبي (Category)
        $this->category = Category::firstOrCreate(
            ['name' => 'Testing Category'],
            ['sort_order' => 1]
        );
    }

    /**
     * إنشاء مستخدم تجريبي مستوفٍ لكل حقول قاعدة البيانات غير المسموح فيها بـ null
     */
    protected function createTestUser(array $override = []): User
    {
        static $uniqueId = 1;
        $uniqueId++;

        return User::create(array_merge([
            'name'       => 'Student ' . $uniqueId,
            'first_name' => 'Student',
            'middle_name' => 'User',
            'last_name'  => (string) $uniqueId,
            'email'      => "student_{$uniqueId}_" . time() . "@example.com",
            'password'   => Hash::make('password123'),
            'country'    => 'EGP',
            'birth_date' => '2000-01-01',
            'phone'      => '010000000' . rand(10, 99),
        ], $override));
    }

    /**
     * اختبار 1: الكورسات المجانية تُفعل اشتراكاً سيرفرياً مباشراً دون استدعاء البوابة
     */
    public function test_free_course_auto_subscribes_user_without_easykash_payment(): void
    {
        $user = $this->createTestUser();
        $course = Course::create([
            'category_id' => $this->category->id,
            'title'       => 'Free Test Course',
            'is_free'     => true,
            'price'       => 0,
            'is_active'   => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/courses/{$course->id}/pay");

        $response->assertStatus(200)
            ->assertJson([
                'status'        => true,
                'is_subscribed' => true,
            ]);

        $this->assertDatabaseHas('course_subscriptions', [
            'user_id'             => $user->id,
            'course_id'           => $course->id,
            'subscription_status' => 'active',
            'payment_status'      => 'not_required',
        ]);
    }

    /**
     * اختبار 2: السعر يُحسب حصرياً من قاعدة البيانات وتجاهل سعر العميل المُتلاعب به
     */
    public function test_paid_course_initiation_uses_server_db_price_ignoring_client(): void
    {
        Config::set('payment.gateway', 'easykash');
        Config::set('payment.easykash.secret_key', 'test_secret');

        $user = $this->createTestUser(['country' => 'EGP']);
        $course = Course::create([
            'category_id' => $this->category->id,
            'title'       => 'Paid Test Course',
            'is_free'     => false,
            'price'       => 500.00,
            'prices'      => ['EGP' => 500],
            'is_active'   => true,
        ]);

        // يحاول الطالب إرسال سعر مُتلاعب به = 1 EGP
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/courses/{$course->id}/pay", [
                'price'  => 1,
                'amount' => 1,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data'   => [
                    'amount' => 500,
                ],
            ]);

        // التأكد من تسجيل معاملة المعاينة بسعر قاعدة البيانات 500 EGP
        $this->assertDatabaseHas('transactions', [
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'amount'    => 500.00,
            'status'    => 'pending',
        ]);

        // الاشتراك يُنشأ معلقاً (pending)
        $this->assertDatabaseHas('course_subscriptions', [
            'user_id'             => $user->id,
            'course_id'           => $course->id,
            'subscription_status' => 'pending',
            'payment_status'      => 'pending',
        ]);
    }

    public function test_wallet_payment_insufficient_balance_returns_authoritative_amount_breakdown(): void
    {
        $user = $this->createTestUser([
            'country' => 'EGP',
            'wallet_balance' => 100,
        ]);
        $course = Course::create([
            'category_id' => $this->category->id,
            'title'       => 'Wallet Balance Test Course',
            'is_free'     => false,
            'price'       => 250.00,
            'prices'      => ['EGP' => 250.00],
            'is_active'   => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/courses/{$course->id}/pay-with-wallet");

        $response->assertStatus(400)
            ->assertJson([
                'status'          => false,
                'wallet_balance'  => 100,
                'required_amount' => 250,
                'currency_code'   => 'EGP',
                'currency_symbol' => 'ج.م',
            ]);
    }

    public function test_wallet_payment_success_returns_paid_amount_and_remaining_balance(): void
    {
        $user = $this->createTestUser([
            'country' => 'EGP',
            'wallet_balance' => 500,
        ]);
        $course = Course::create([
            'category_id' => $this->category->id,
            'title'       => 'Wallet Payment Success Test Course',
            'is_free'     => false,
            'price'       => 250.00,
            'prices'      => ['EGP' => 250.00],
            'is_active'   => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/courses/{$course->id}/pay-with-wallet");

        $response->assertStatus(200)
            ->assertJson([
                'status'         => true,
                'is_subscribed'  => true,
                'wallet_balance' => 250,
                'amount'         => 250,
                'currency_code'  => 'EGP',
                'currency_symbol'=> 'ج.م',
            ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'payment_gateway' => 'wallet',
            'status' => Transaction::STATUS_COMPLETED,
        ]);
        $this->assertDatabaseHas('course_subscriptions', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'plan_id' => null,
            'subscription_status' => CourseSubscription::STATUS_ACTIVE,
            'payment_status' => CourseSubscription::PAYMENT_PAID,
            'payment_gateway' => 'wallet',
        ]);

        $subscription = CourseSubscription::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->firstOrFail();
        $this->assertNotNull($subscription->group_id);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/courses/{$course->id}/subscription-status")
            ->assertOk()
            ->assertJsonPath('is_subscribed', true)
            ->assertJsonPath('group.id', $subscription->group_id);
    }

    public function test_wallet_quote_and_payment_refresh_plan_price_and_reject_a_stale_confirmation(): void
    {
        $user = $this->createTestUser([
            'country' => 'المملكة العربية السعودية',
            'wallet_balance' => 500,
        ]);
        $course = Course::create([
            'category_id' => $this->category->id,
            'title' => 'Updated Wallet Plan Price Course',
            'is_free' => false,
            'price' => 300,
            'prices' => ['EGP' => 300, 'SAR' => 100],
            'is_active' => true,
        ]);
        $plan = CoursePlan::create([
            'course_id' => $course->id,
            'name' => 'Saudi Wallet Plan',
            'slug' => 'saudi-wallet-plan-' . $course->id,
            'price' => 300,
            'currency' => 'EGP',
            'prices' => ['EGP' => 300, 'SAR' => 80],
            'duration_days' => 30,
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/courses/{$course->id}/plans")
            ->assertOk()
            ->assertJsonPath('data.0.price', 80)
            ->assertJsonPath('data.0.currency_code', 'SAR');

        $this->getJson("/api/courses/{$course->id}/wallet-quote?plan_id={$plan->id}")
            ->assertOk()
            ->assertJsonPath('amount', 80)
            ->assertJsonPath('currency_code', 'SAR')
            ->assertJsonPath('plan.name', 'Saudi Wallet Plan');

        $plan->update(['prices' => ['EGP' => 300, 'SAR' => 120]]);

        $this->postJson("/api/courses/{$course->id}/pay-with-wallet", [
            'plan_id' => $plan->id,
            'expected_amount' => 80,
        ])
            ->assertStatus(409)
            ->assertJsonPath('price_changed', true)
            ->assertJsonPath('amount', 120);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'wallet_balance' => 500,
        ]);
        $this->assertDatabaseCount('wallet_transactions', 0);

        $this->postJson("/api/courses/{$course->id}/pay-with-wallet", [
            'plan_id' => $plan->id,
            'expected_amount' => 120,
        ])
            ->assertOk()
            ->assertJsonPath('amount', 120)
            ->assertJsonPath('currency_code', 'SAR')
            ->assertJsonPath('wallet_balance', 380);
    }

    public function test_wallet_payment_uses_the_updated_course_price_after_quote_confirmation(): void
    {
        $user = $this->createTestUser([
            'country' => 'EGP',
            'wallet_balance' => 500,
        ]);
        $course = Course::create([
            'category_id' => $this->category->id,
            'title' => 'Updated Wallet Course Price',
            'is_free' => false,
            'price' => 250,
            'prices' => ['EGP' => 250],
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/courses/{$course->id}/wallet-quote")
            ->assertOk()
            ->assertJsonPath('amount', 250);

        Transaction::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'amount' => 250,
            'currency_code' => 'EGP',
            'payment_gateway' => 'wallet',
            'status' => Transaction::STATUS_COMPLETED,
            'payload' => ['plan_id' => null, 'plan_duration_days' => 30],
        ]);

        $course->update([
            'price' => 400,
            'prices' => ['EGP' => 400],
        ]);

        $this->postJson("/api/courses/{$course->id}/pay-with-wallet", [
            'expected_amount' => 250,
        ])
            ->assertStatus(409)
            ->assertJsonPath('price_changed', true)
            ->assertJsonPath('amount', 400);

        $this->postJson("/api/courses/{$course->id}/pay-with-wallet", [
            'expected_amount' => 400,
        ])
            ->assertOk()
            ->assertJsonPath('amount', 400)
            ->assertJsonPath('wallet_balance', 100);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_wallet_payment_activates_plan_subscription_and_assigns_its_group(): void
    {
        $user = $this->createTestUser([
            'country' => 'EGP',
            'wallet_balance' => 500,
        ]);
        $course = Course::create([
            'category_id' => $this->category->id,
            'title' => 'Wallet Plan Subscription Test Course',
            'is_free' => false,
            'price' => 250.00,
            'prices' => ['EGP' => 250.00],
            'is_active' => true,
        ]);
        $plan = CoursePlan::create([
            'course_id' => $course->id,
            'name' => 'Wallet Test Plan',
            'slug' => 'wallet-test-plan-' . $course->id,
            'price' => 250.00,
            'currency' => 'EGP',
            'duration_days' => 30,
            'is_active' => true,
        ]);
        $level = Level::create([
            'course_id' => $course->id,
            'plan_id' => $plan->id,
            'title' => 'Wallet Plan Level',
            'order_num' => 1,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/courses/{$course->id}/pay-with-wallet", [
                'plan_id' => $plan->id,
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => true,
                'is_subscribed' => true,
            ]);

        $subscription = CourseSubscription::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('plan_id', $plan->id)
            ->firstOrFail();

        $this->assertSame(CourseSubscription::STATUS_ACTIVE, $subscription->subscription_status);
        $this->assertSame(CourseSubscription::PAYMENT_PAID, $subscription->payment_status);
        $this->assertNotNull($subscription->group_id);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/courses/{$course->id}/subscription-status?plan_id={$plan->id}")
            ->assertOk()
            ->assertJsonPath('is_subscribed', true)
            ->assertJsonPath('group.id', $subscription->group_id);

        \App\Models\CourseGroup::whereKey($subscription->group_id)->update(['status' => 'active']);
        $this->getJson("/api/levels/{$course->id}?plan_id={$plan->id}")
            ->assertOk()
            ->assertJsonFragment([
                'id' => $level->id,
                'title' => 'Wallet Plan Level',
            ]);
    }

    public function test_retries_repair_legacy_completed_wallet_payment_without_charging_again(): void
    {
        $user = $this->createTestUser([
            'country' => 'EGP',
            'wallet_balance' => 250,
        ]);
        $course = Course::create([
            'category_id' => $this->category->id,
            'title' => 'Legacy Wallet Recovery Course',
            'is_free' => false,
            'price' => 250,
            'prices' => ['EGP' => 250],
            'is_active' => true,
        ]);
        Transaction::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'amount' => 250,
            'currency_code' => 'EGP',
            'payment_gateway' => 'wallet',
            'status' => Transaction::STATUS_COMPLETED,
            'payload' => [
                'plan_id' => null,
                'plan_duration_days' => 30,
            ],
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/courses/{$course->id}/pay-with-wallet")
            ->assertOk()
            ->assertJsonPath('is_subscribed', true)
            ->assertJsonPath('wallet_balance', 250);

        $this->assertDatabaseHas('course_subscriptions', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'subscription_status' => CourseSubscription::STATUS_ACTIVE,
            'payment_status' => CourseSubscription::PAYMENT_PAID,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'wallet_balance' => 250,
        ]);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    /**
     * اختبار 3: يمنع المستخدم غير المشترك من الوصول للمستويات بإرجاع 403 Forbidden
     */
    public function test_unsubscribed_user_receiving_403_on_course_levels(): void
    {
        $user = $this->createTestUser();
        $course = Course::create([
            'category_id' => $this->category->id,
            'title'       => 'Protected Content Course',
            'is_free'     => false,
            'price'       => 300,
            'is_active'   => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/levels/{$course->id}");

        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
            ]);
    }

    /**
     * اختبار 4: يمنح المستخدم المشترك اشتراكاً فعالاً وصولاً للمحتوى بإرجاع 200 OK
     */
    public function test_subscribed_user_granted_access_to_levels(): void
    {
        $user = $this->createTestUser();
        $course = Course::create([
            'category_id' => $this->category->id,
            'title'       => 'Accessible Course',
            'is_free'     => false,
            'price'       => 300,
            'is_active'   => true,
        ]);

        CourseSubscription::create([
            'user_id'             => $user->id,
            'course_id'           => $course->id,
            'subscription_status' => 'active',
            'payment_status'      => 'paid',
            'amount'              => 300,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/levels/{$course->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
            ]);
    }

    public function test_course_levels_defer_quiz_payload_until_a_single_lesson_is_requested(): void
    {
        $user = $this->createTestUser();
        $course = Course::create([
            'category_id' => $this->category->id,
            'title'       => 'Quiz Loading Course',
            'is_free'     => false,
            'price'       => 300,
            'is_active'   => true,
        ]);
        CourseSubscription::create([
            'user_id'             => $user->id,
            'course_id'           => $course->id,
            'subscription_status' => 'active',
            'payment_status'      => 'paid',
            'amount'              => 300,
        ]);
        $level = Level::create([
            'course_id' => $course->id,
            'title'     => 'Level One',
            'order_num' => 1,
        ]);
        $lesson = \App\Models\Lesson::create([
            'level_id'  => $level->id,
            'title'     => 'Lesson One',
            'order_num' => 1,
        ]);
        $otherLesson = \App\Models\Lesson::create([
            'level_id'  => $level->id,
            'title'     => 'Lesson Two',
            'order_num' => 2,
        ]);
        $question = \App\Models\Question::create([
            'lesson_id'    => $lesson->id,
            'question_text' => 'Question one?',
        ]);
        \App\Models\Option::create([
            'question_id' => $question->id,
            'option_text' => 'Answer one',
            'is_correct'  => true,
        ]);
        \App\Models\Question::create([
            'lesson_id'     => $otherLesson->id,
            'question_text' => 'Question two?',
        ]);

        $levelsResponse = $this->actingAs($user, 'sanctum')
            ->getJson("/api/levels/{$course->id}");

        $levelsResponse->assertOk()
            ->assertJsonPath('data.0.lessons.0.questions_count', 1);
        $this->assertArrayNotHasKey('questions', $levelsResponse->json('data.0.lessons.0'));
        $this->assertArrayNotHasKey('questions', $levelsResponse->json('data.0.lessons.1'));

        $lessonResponse = $this->actingAs($user, 'sanctum')
            ->getJson("/api/levels/{$level->id}/lessons?lesson_id={$lesson->id}");

        $lessonResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lesson->id)
            ->assertJsonPath('data.0.questions.0.options.0.option_text', 'Answer one');
    }

    public function test_each_plan_can_be_subscribed_and_only_its_content_is_returned(): void
    {
        $user = $this->createTestUser();
        $course = Course::create([
            'category_id' => $this->category->id,
            'title' => 'Multiple Plans Course',
            'is_free' => false,
            'price' => 300,
            'is_active' => true,
        ]);
        $firstPlan = CoursePlan::create([
            'course_id' => $course->id,
            'name' => 'Plan One',
            'slug' => 'multiple-plans-one-' . $course->id,
            'price' => 100,
            'duration_days' => 30,
            'is_active' => true,
        ]);
        $secondPlan = CoursePlan::create([
            'course_id' => $course->id,
            'name' => 'Plan Two',
            'slug' => 'multiple-plans-two-' . $course->id,
            'price' => 0,
            'duration_days' => 30,
            'is_active' => true,
            'is_free' => true,
        ]);
        $firstLevel = Level::create([
            'course_id' => $course->id,
            'plan_id' => $firstPlan->id,
            'title' => 'Plan One Level',
            'order_num' => 1,
        ]);
        $secondLevel = Level::create([
            'course_id' => $course->id,
            'plan_id' => $secondPlan->id,
            'title' => 'Plan Two Level',
            'order_num' => 1,
        ]);

        CourseSubscription::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'plan_id' => $firstPlan->id,
            'subscription_status' => CourseSubscription::STATUS_ACTIVE,
            'payment_status' => CourseSubscription::PAYMENT_PAID,
            'amount' => 100,
            'started_at' => now(),
            'expired_at' => now()->addDays(30),
        ]);

        $plansBeforePurchase = $this->actingAs($user, 'sanctum')
            ->getJson("/api/courses/{$course->id}/plans");
        $plansBeforePurchase->assertOk()
            ->assertJsonPath('data.0.is_subscribed', true)
            ->assertJsonPath('data.1.is_subscribed', false);

        $this->getJson("/api/levels/{$course->id}?plan_id={$secondPlan->id}")
            ->assertForbidden();

        $this->postJson("/api/courses/{$course->id}/pay", [
            'plan_id' => $secondPlan->id,
        ])->assertOk()->assertJsonPath('data.plan_id', $secondPlan->id);

        $this->assertDatabaseHas('course_subscriptions', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'plan_id' => $firstPlan->id,
            'subscription_status' => CourseSubscription::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('course_subscriptions', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'plan_id' => $secondPlan->id,
            'subscription_status' => CourseSubscription::STATUS_ACTIVE,
        ]);

        $secondSubscription = CourseSubscription::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('plan_id', $secondPlan->id)
            ->firstOrFail();
        $secondSubscription->group->update(['status' => 'active']);

        $levels = $this->getJson("/api/levels/{$course->id}?plan_id={$secondPlan->id}");
        $levels->assertOk()->assertJsonFragment([
            'id' => $secondLevel->id,
            'title' => 'Plan Two Level',
        ])->assertJsonMissing([
            'id' => $firstLevel->id,
            'title' => 'Plan One Level',
        ]);
    }

    /**
     * اختبار 5: استلام الـ Callback بنجاح مع توقيع HMAC تُمكّن تفعيل الاشتراك رسمياً
     */
    public function test_easykash_get_callback_activates_subscription(): void
    {
        Config::set('payment.easykash.secret_key', 'test_secret_key');
        Config::set('payment.easykash.mode', 'sandbox');

        $user = $this->createTestUser();
        $course = Course::create([
            'category_id' => $this->category->id,
            'title'       => 'Callback Test Course',
            'is_free'     => false,
            'price'       => 250,
            'is_active'   => true,
        ]);

        $transaction = Transaction::create([
            'user_id'                => $user->id,
            'course_id'              => $course->id,
            'amount'                 => 250.00,
            'currency_code'          => 'EGP',
            'payment_gateway'        => 'easykash',
            'gateway_transaction_id' => 'EK-100200',
            'status'                 => 'pending',
        ]);

        CourseSubscription::create([
            'user_id'                => $user->id,
            'course_id'              => $course->id,
            'subscription_status'    => 'pending',
            'payment_status'         => 'pending',
            'amount'                 => 250.00,
            'gateway_transaction_id' => 'EK-100200',
        ]);

        // بناء توقيع HMAC-SHA256 صالح
        $secret = 'test_secret_key';
        $orderId = 'EK-100200';
        $amount = '250.00';
        $currency = 'EGP';
        $status = 'completed';
        $signature = hash_hmac('sha256', $orderId . '|' . $amount . '|' . $currency . '|' . $status, $secret);

        $response = $this->getJson("/api/payments/easykash/callback?gateway_transaction_id=EK-100200&status=completed&amount=250.00&currency=EGP&signature={$signature}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data'   => [
                    'payment_status'      => 'paid',
                    'subscription_status' => 'active',
                ],
            ]);

        $this->assertDatabaseHas('course_subscriptions', [
            'user_id'             => $user->id,
            'course_id'           => $course->id,
            'subscription_status' => 'active',
            'payment_status'      => 'paid',
        ]);

        $this->assertDatabaseHas('transactions', [
            'id'     => $transaction->id,
            'status' => 'completed',
        ]);
    }

    /**
     * اختبار 6: وصول الـ Callback مكرراً لا يؤدي لإنشاء اشتراك مكرر (Idempotency Test)
     */
    public function test_duplicate_callback_does_not_duplicate_subscription(): void
    {
        Config::set('payment.easykash.secret_key', 'test_secret_key');
        Config::set('payment.easykash.mode', 'sandbox');

        $user = $this->createTestUser();
        $course = Course::create([
            'category_id' => $this->category->id,
            'title'       => 'Idempotency Test Course',
            'is_free'     => false,
            'price'       => 400,
            'is_active'   => true,
        ]);

        $transaction = Transaction::create([
            'user_id'                => $user->id,
            'course_id'              => $course->id,
            'amount'                 => 400.00,
            'currency_code'          => 'EGP',
            'payment_gateway'        => 'easykash',
            'gateway_transaction_id' => 'EK-IDEMP-99',
            'status'                 => 'completed',
        ]);

        CourseSubscription::create([
            'user_id'                => $user->id,
            'course_id'              => $course->id,
            'subscription_status'    => 'active',
            'payment_status'         => 'paid',
            'amount'                 => 400.00,
            'gateway_transaction_id' => 'EK-IDEMP-99',
        ]);

        $signature = hash_hmac('sha256', 'EK-IDEMP-99|400.00|EGP|completed', 'test_secret_key');

        // إرسال Callback مكرر مرة ثانية
        $response = $this->getJson("/api/payments/easykash/callback?gateway_transaction_id=EK-IDEMP-99&status=completed&amount=400.00&currency=EGP&signature={$signature}");

        $response->assertStatus(200);

        // التأكد من عدم وجود أكثر من سجل اشتراك واحد
        $this->assertEquals(1, CourseSubscription::where('user_id', $user->id)->where('course_id', $course->id)->count());
    }
}
