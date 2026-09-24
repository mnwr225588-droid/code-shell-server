<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseUser;
use App\Models\Transaction;
use App\Services\EasyKashService;
use App\Services\Payment\Gateways\SandboxGateway;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\PricingService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            $returnUrl = (string) config('payment.easykash.callback_url', config('payment.return_url', 'https://code-shell-server-production.up.railway.app/api/payments/easykash/callback'));
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
     * - يقرأ مرجع العميل القادم في رابط العودة (customerReference أو merchant_order_id).
     * - يفعل اشتراك الكورس فوراً في قاعدة البيانات بشكل مباشر وتضميني.
     * - يعيد توجيه المستخدم تلقائياً لصفحة الكورسات.
     */
    public function easykashCallback(Request $request)
    {
        $params = $request->all();
        Log::info('EasyKash Callback Received: ', $params);

        // رابط الواجهة الأمامية لإعادة توجيه الطالب إليها
        $frontendUrl = 'https://codeshell.kesug.com/courses.html';

        // 1. استخراج مرجع المعاملة المحتفل به من EasyKash
        $orderRef = $request->input('customerReference') 
                 ?? $request->input('merchant_order_id') 
                 ?? $request->input('order_id')
                 ?? $request->input('gateway_transaction_id');

        $transactionId = null;
        if ($orderRef && preg_match('/CS-TX-(\d+)-/', $orderRef, $matches)) {
            $transactionId = (int) $matches[1];
        }

        // البحث عن المعاملة المحلية في قاعدة البيانات
        $transaction = null;
        if ($transactionId) {
            $transaction = Transaction::find($transactionId);
        }

        if (!$transaction && $orderRef) {
            $transaction = Transaction::where('gateway_transaction_id', $orderRef)->first();
        }

        if (!$transaction) {
            Log::error('EasyKash Callback Error: Transaction not found for ref: ' . ($orderRef ?? 'null'));
            return redirect($frontendUrl . '?status=failed&message=' . urlencode('عذراً، المعاملة المالية غير مسجلة لدينا.'));
        }

        // 2. فحص حالة العملية القادمة من البوابة
        $rawStatus = strtoupper((string) ($request->input('status') ?? $request->input('payment_status') ?? $request->input('state') ?? ''));
        $isExplicitFailure = in_array($rawStatus, ['FAILED', 'DECLINED', 'ERROR', 'CANCELLED', 'CANCELED', 'FALSE', '0']);
        $isSuccess = in_array($rawStatus, ['PAID', 'SUCCESS', 'COMPLETED', 'SUCCESSFUL', 'TRUE', '1']) || empty($rawStatus) || $rawStatus === 'SUCCESS';
        
        if ($isExplicitFailure) {
            $isSuccess = false;
        }

        if ($isSuccess) {
            DB::beginTransaction();
            try {
                // تحديث حالة المعاملة إلى مكتملة
                $transaction->update([
                    'status'                 => Transaction::STATUS_COMPLETED ?? 'completed',
                    'paid_at'                => now(),
                    'gateway_transaction_id' => $request->input('providerRefNum') ?? $orderRef ?? $transaction->gateway_transaction_id,
                ]);

                // تفعيل الاشتراك عبر خدمة الاشتراكات إن وجدت
                if (isset($this->subscriptionService)) {
                    try {
                        $this->subscriptionService->activateFromTransaction($transaction, $params);
                    } catch (\Throwable $subEx) {
                        Log::warning('SubscriptionService activation warning: ' . $subEx->getMessage());
                    }
                }

                // كتابة الاشتراك وتفعيله فوراً ومباشرة داخل DB (تأكيد القيد في course_user)
                if ($transaction->user_id && $transaction->course_id) {
                    CourseUser::updateOrCreate(
                        [
                            'user_id'   => $transaction->user_id,
                            'course_id' => $transaction->course_id,
                        ],
                        [
                            'status'        => 'active',
                            'subscribed_at' => now(),
                            'updated_at'    => now(),
                        ]
                    );
                }

                DB::commit();
                Log::info("EasyKash Payment Completed & Course Activated for User #{$transaction->user_id}, Course #{$transaction->course_id}");

                // توجيه الطالب مباشرة إلى واجهة الموقع مع معلمات النجاح
                return redirect($frontendUrl . '?status=success&course_id=' . $transaction->course_id . '&tx=' . $transaction->id);

            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('EasyKash Subscription Activation Failed: ' . $e->getMessage());
                return redirect($frontendUrl . '?status=failed&message=' . urlencode('حدث خطأ أثناء تفعيل الكورس.'));
            }
        }

        // في حالة الفشل أو الإلغاء
        $this->subscriptionService->markFailedFromTransaction($transaction, 'Payment returned status: ' . $rawStatus);
        return redirect($frontendUrl . '?status=failed&message=' . urlencode('لم تتم عملية الدفع بنجاح.'));
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