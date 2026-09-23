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
use App\Models\CourseSubscription;
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
