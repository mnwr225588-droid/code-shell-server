<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Transaction;
use App\Services\EasyKashService;
use App\Services\Payment\Gateways\SandboxGateway;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\PricingService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct()
    {
        $this->subscriptionService = new SubscriptionService();
    }

    /**
     * بدء عملية الدفع: POST /api/courses/{id}/pay
     *
     * الأمان الصارم:
     * 1. السعر والعملة يُحسبان حصرياً على السيرفر من قاعدة البيانات (courses.price) ومصفوفة أسعار السيرفر.
     * 2. أي سعر قادم من تطبيق العميل (Flutter/Postman) يتم تجاهله تماماً.
     * 3. إذا كان الكورس مجانياً، يتم تفعيل الاشتراك المجاني مباشرة عبر السيرفر.
     * 4. إنشاء معاملة واشتراك بحالة pending معلقة حتى التأكيد السيرفري من EasyKash.
     */
    public function initiate(Request $request, $courseId): JsonResponse
    {
        $user = $request->user();
        $course = Course::findOrFail($courseId);

        // 1. الكورسات المجانية: إنشاء اشتراك مجاني فعال عبر السيرفر دون استدعاء EasyKash
        if ($course->is_free || (float)$course->price === 0.0) {
            $freeSub = $this->subscriptionService->createFreeSubscription($user, $course);

            return response()->json([
                'status'             => true,
                'message'            => 'تم تفعيل الاشتراك في الكورس المجاني بنجاح!',
                'already_subscribed' => true,
                'is_subscribed'      => true,
                'data'               => [
                    'subscription_id' => $freeSub->id,
                    'course_id'       => $course->id,
                    'amount'          => 0.0,
                    'status'          => 'active',
                ],
            ]);
        }

        // 2. التحقق من وجود اشتراك نشط سابق
        if ($course->isUserSubscribed($user->id)) {
            return response()->json([
                'status'             => false,
                'message'            => 'أنت مشترك بالفعل في هذا الكورس.',
                'already_subscribed' => true,
            ], 422);
        }

        // 3. احتساب السعر والعملة من قاعدة البيانات على السيرفر فقط
        $prices = $course->prices ?? [];
        if (empty($prices)) {
            $prices = PricingService::defaults();
        }
        $pricing = PricingService::priceFor($user->country, $prices);
        $amount = $pricing['price'];
        $currency = $pricing['currency_code'];

        $gateway = PaymentGatewayManager::active();

        // 4. فحص المعاملات المعلقة السابقة لنفس المستخدم والكورس للحد من الجلسات المكررة
        $pending = Transaction::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', Transaction::STATUS_PENDING)
            ->where('payment_gateway', $gateway->name())
            ->latest()
            ->first();

        if ($pending && $pending->gateway_transaction_id) {
            $url = $pending->payload['payment_url'] ?? null;
            if ($url) {
                return response()->json([
                    'status'  => true,
                    'message' => 'استئناف جلسة دفع سابقة معلقة.',
                    'data'    => [
                        'transaction_id'  => $pending->id,
                        'payment_url'     => $url,
                        'amount'          => (float) $pending->amount,
                        'currency_code'   => $pending->currency_code,
                        'currency_symbol' => PricingService::symbol($pending->currency_code),
                    ],
                ]);
            }
        }

        // 5. إنشاء معاملة مالية جديدة في DB
        $transaction = new Transaction([
            'user_id'         => $user->id,
            'course_id'       => $course->id,
            'amount'          => $amount,
            'currency_code'   => $currency,
            'payment_gateway' => $gateway->name(),
            'status'          => Transaction::STATUS_PENDING,
            'payload'         => [
                'auto_assign_group' => true,
                'course_price'      => $amount,
            ],
        ]);
        $transaction->save();

        // 6. إنشاء سجل اشتراك بحالة pending (معلق) لحين التحقق
        $this->subscriptionService->initiatePaymentSubscription($user, $course, $transaction);

        try {
            $returnUrl = (string) config('payment.easykash.callback_url', config('payment.return_url', ''));
            $created = $gateway->createPayment($transaction, $returnUrl);
        } catch (\Throwable $e) {
            $transaction->update([
                'status'  => Transaction::STATUS_FAILED,
                'payload' => array_merge($transaction->payload ?? [], ['error' => $e->getMessage()]),
            ]);
            $this->subscriptionService->markFailedFromTransaction($transaction, $e->getMessage());
            throw $e;
        }

        $transaction->update([
            'gateway_transaction_id' => $created['gateway_transaction_id'],
            'payload'                => array_merge($transaction->payload ?? [], $created['payload'] ?? []),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'تم تجهيز جلسة الدفع بنجاح.',
            'data'    => [
                'transaction_id'  => $transaction->id,
                'payment_url'     => $created['payment_url'],
                'amount'          => (float) $transaction->amount,
                'currency_code'   => $transaction->currency_code,
                'currency_symbol' => PricingService::symbol($transaction->currency_code),
            ],
        ]);
    }

    /**
     * مسار Callback الخاص بـ EasyKash: GET /api/payments/easykash/callback
     *
     * - يعمل عبر HTTP GET حصرياً وفق متطلبات EasyKash.
     * - يتحقق سيرفرياً من التوقيع HMAC ومطابقة المبلغ والمعاملة.
     * - يضمن عدم تكرار التفعيل (Idempotency).
     */
    public function easykashCallback(Request $request)
    {
        $params = $request->all();
        $easyKashService = new EasyKashService();

        // 1. التحقق من التوقيع الرقمي / HMAC
        $isValidSignature = $easyKashService->validateSignature($params, null);

        $gatewayTxId = (string) ($params['gateway_transaction_id'] 
            ?? $params['transaction_id'] 
            ?? $params['merchant_order_id'] 
            ?? '');

        // البحث عن المعاملة المحلية في DB
        $transaction = null;
        if ($gatewayTxId) {
            $transaction = Transaction::where('gateway_transaction_id', $gatewayTxId)->first();
        }

        if (!$transaction && !empty($params['merchant_order_id'])) {
            // محاولة استخراج ID المعاملة من merchant_order_id (مثال: CS-TX-123-...)
            if (preg_match('/CS-TX-(\d+)-/', $params['merchant_order_id'], $matches)) {
                $transaction = Transaction::find($matches[1]);
            }
        }

        if (!$transaction) {
            return response()->json([
                'status'  => false,
                'message' => 'عذراً، لم يتم العثور على المعاملة المالية المرتبطة بهذا الطلب.',
            ], 404);
        }

        if (!$isValidSignature && config('payment.easykash.mode') !== 'sandbox') {
            $this->subscriptionService->markFailedFromTransaction($transaction, 'HMAC signature mismatch');
            return response()->json([
                'status'  => false,
                'message' => 'فشل التحقق من توقيع EasyKash (Invalid Signature).',
            ], 400);
        }

        // 2. فحص حالة المعاملة من EasyKash أو البارامترات
        $statusRaw = strtolower((string) ($params['status'] ?? 'completed'));
        $isSuccess = in_array($statusRaw, ['paid', 'completed', 'success', 'successful', 'true', '1']);

        if ($isSuccess) {
            // تفعيل الاشتراك والمعاملة سيرفرياً وقفل الصف
            $this->subscriptionService->activateFromTransaction($transaction, $params);

            return response()->json([
                'status'  => true,
                'message' => 'تم تأكيد الدفع وتفعيل الاشتراك في الكورس بنجاح!',
                'data'    => [
                    'transaction_id'      => $transaction->id,
                    'course_id'           => $transaction->course_id,
                    'payment_status'      => 'paid',
                    'subscription_status' => 'active',
                ],
            ]);
        }

        // في حالة الفشل أو الإلغاء
        $this->subscriptionService->markFailedFromTransaction($transaction, 'Payment returned status: ' . $statusRaw);

        return response()->json([
            'status'  => false,
            'message' => 'عذراً، لم تكتمل عملية الدفع أو تم إلغاؤها.',
            'data'    => [
                'transaction_id'      => $transaction->id,
                'payment_status'      => 'failed',
                'subscription_status' => 'pending',
            ],
        ], 400);
    }

    /**
     * استقبال إشعارات الـ Webhook العام (POST /api/payment/webhook)
     */
    public function webhook(Request $request): JsonResponse
    {
        $gateway = PaymentGatewayManager::active();

        try {
            $result = $gateway->handleWebhook($request);
        } catch (RuntimeException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Webhook غير صالح: ' . $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'خطأ في معالجة الـ Webhook.',
            ], 500);
        }

        $transaction = Transaction::where(
            'gateway_transaction_id',
            $result['gateway_transaction_id']
        )->first();

        if (!$transaction) {
            return response()->json([
                'status'  => false,
                'message' => 'معاملة غير معروفة.',
            ], 404);
        }

        if ($result['status'] === Transaction::STATUS_COMPLETED) {
            $this->subscriptionService->activateFromTransaction($transaction, $result['raw_payload'] ?? []);
        } else {
            $this->subscriptionService->markFailedFromTransaction($transaction, 'Webhook status: ' . $result['status']);
        }

        return response()->json(['status' => true]);
    }

    /**
     * حالة آخر معاملة للمستخدم على كورس معين: GET /api/courses/{id}/payment-status
     */
    public function paymentStatus(Request $request, $courseId): JsonResponse
    {
        $user = $request->user();
        $course = Course::findOrFail($courseId);

        $transaction = Transaction::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->latest()
            ->first();

        $isSubscribed = $course->isUserSubscribed($user->id);

        return response()->json([
            'status' => true,
            'data'   => [
                'is_subscribed'      => $isSubscribed,
                'transaction_status' => $transaction?->status,
                'amount'             => $transaction ? (float) $transaction->amount : null,
                'currency_code'      => $transaction?->currency_code,
                'currency_symbol'    => $transaction ? PricingService::symbol($transaction->currency_code) : null,
            ],
        ]);
    }

    /**
     * صفحة محاكي Sandbox
     */
    public function sandboxPage(Request $request, $ref)
    {
        $transaction = Transaction::where('gateway_transaction_id', $ref)
            ->where('status', Transaction::STATUS_PENDING)
            ->first();

        if (!$transaction || $transaction->payment_gateway !== 'sandbox') {
            abort(404, 'جلسة الدفع غير صالحة أو منتهية.');
        }

        $courseTitle = $transaction->course?->title ?? 'الكورس';
        $amount = rtrim(rtrim(number_format((float) $transaction->amount, 2), '0'), '.');
        $symbol = PricingService::symbol($transaction->currency_code);
        $webhookUrl = e(SandboxGateway::webhookUrl());
        $secret = (string) config('payment.sandbox.secret');

        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        $completedPayload = json_encode([
            'gateway_transaction_id' => $ref,
            'status'                 => 'completed',
            'event'                  => 'payment.completed',
        ], $jsonFlags);

        $completedSigned = json_encode([
            'gateway_transaction_id' => $ref,
            'status'                 => 'completed',
            'event'                  => 'payment.completed',
            'signature'              => hash_hmac('sha256', $completedPayload, $secret),
        ], $jsonFlags);

        $failedPayload = json_encode([
            'gateway_transaction_id' => $ref,
            'status'                 => 'failed',
            'event'                  => 'payment.failed',
        ], $jsonFlags);

        $failedSigned = json_encode([
            'gateway_transaction_id' => $ref,
            'status'                 => 'failed',
            'event'                  => 'payment.failed',
            'signature'              => hash_hmac('sha256', $failedPayload, $secret),
        ], $jsonFlags);

        return response(
            view('payment.sandbox', compact(
                'courseTitle',
                'amount',
                'symbol',
                'webhookUrl',
                'completedSigned',
                'failedSigned'
            ))
        );
    }
}
