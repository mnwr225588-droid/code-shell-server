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

        $planType = $request->input('plan_type') ?? $request->input('plan') ?? 'monthly';

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
                'plan_type'         => $planType,
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
     */
    public function easykashCallback(Request $request)
    {
        $params = $request->all();
        Log::info('EasyKash Callback Received: ', $params);

        // رابط الواجهة الأمامية لإعادة توجيه الطالب إليها
        $frontendUrl = 'https://codeshell.kesug.com/courses.html';

        // 1. استخراج مرجع المعاملة بكافة الخيارات المتوقعة من البوابة
        $orderRef = $request->input('customerReference') 
                 ?? $request->input('merchant_order_id') 
                 ?? $request->input('order_id')
                 ?? $request->input('orderRef')
                 ?? $request->input('reference')
                 ?? $request->input('ref')
                 ?? $request->input('gateway_transaction_id')
                 ?? $request->input('tx');

        $transactionId = null;
        if ($orderRef && preg_match('/CS-TX-(\d+)-/', $orderRef, $matches)) {
            $transactionId = (int) $matches[1];
        } elseif (is_numeric($orderRef)) {
            $transactionId = (int) $orderRef;
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
            // كخيار أخير: جلب أحدث معاملة معلقة
            $transaction = Transaction::where('status', Transaction::STATUS_PENDING)->latest()->first();
        }

        if (!$transaction) {
            Log::error('EasyKash Callback Error: Transaction not found for ref: ' . ($orderRef ?? 'null'));
            return redirect($frontendUrl . '?status=failed&message=' . urlencode('عذراً، المعاملة المالية غير مسجلة لدينا.'));
        }

        $isWalletTopup = ($transaction->course_id === null) || data_get($transaction->payload, 'is_wallet_topup', false);

        // إذا كانت المعاملة قد اكملت سابقاً بالفعل (مثلاً تم استلام الإشعار عبر webhook أولاً)، نعيد التوجيه فوراً بدون تكرار إضافة الرصيد
        if ($transaction->status === Transaction::STATUS_COMPLETED) {
            $redirectTarget = $isWalletTopup 
                ? 'https://codeshell.kesug.com/wallet.html?status=success&tx=' . $transaction->id 
                : $frontendUrl . '?status=success&course_id=' . $transaction->course_id . '&tx=' . $transaction->id;

            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json([
                    'status' => true,
                    'message' => 'تمت العملية بنجاح سابقاً',
                    'data'   => ['payment_status' => 'paid'],
                ]);
            }
            return redirect($redirectTarget);
        }

        if ($isSuccess) {
            DB::beginTransaction();
            try {
                // تحديث حالة المعاملة إلى مكتملة
                $transaction->update([
                    'status'                 => Transaction::STATUS_COMPLETED,
                    'paid_at'                => now(),
                    'gateway_transaction_id' => $request->input('providerRefNum') ?? $orderRef ?? $transaction->gateway_transaction_id,
                ]);

                $isWalletTopup = ($transaction->course_id === null) || data_get($transaction->payload, 'is_wallet_topup', false);

                if ($isWalletTopup) {
                    $user = \App\Models\User::lockForUpdate()->find($transaction->user_id);
                    if ($user) {
                        $topupAmount = (float) $transaction->amount;
                        $user->wallet_balance = (float) ($user->wallet_balance ?? 0) + $topupAmount;
                        $user->save();

                        \App\Models\WalletTransaction::create([
                            'user_id'       => $user->id,
                            'type'          => 'credit',
                            'amount'        => $topupAmount,
                            'balance_after' => (float) $user->wallet_balance,
                            'reference_id'  => (string) $transaction->id,
                            'description'   => "شحن المحفظة عبر الدفع الإلكتروني (مرجع: {$transaction->gateway_transaction_id})",
                        ]);
                        Log::info("Wallet Topup Completed via Callback: User #{$user->id} credited with {$topupAmount} EGP (Tx #{$transaction->id})");
                    }
                } else {
                    // تفعيل الاشتراك صراحة عبر خدمة الاشتراكات
                    if (isset($this->subscriptionService)) {
                        $this->subscriptionService->activateFromTransaction($transaction, $params);
                    }

                    // ضمان إضافي مباشر لحفظ الاشتراك في قاعدة البيانات
                    DB::table('course_subscriptions')->updateOrInsert(
                        ['user_id' => $transaction->user_id, 'course_id' => $transaction->course_id],
                        [
                            'subscription_status' => 'active',
                            'payment_status'      => 'paid',
                            'amount'              => $transaction->amount,
                            'currency_code'       => $transaction->currency_code ?? 'EGP',
                            'payment_gateway'     => $transaction->payment_gateway ?? 'easykash',
                            'paid_at'             => now(),
                            'updated_at'          => now(),
                        ]
                    );
                }

                DB::commit();
                Log::info("Payment Completed & Processed for User #{$transaction->user_id}");

                if ($request->wantsJson() || $request->expectsJson()) {
                    return response()->json([
                        'status' => true,
                        'message' => 'تمت العملية بنجاح',
                        'data'   => [
                            'payment_status'      => 'paid',
                        ],
                    ]);
                }

                // توجيه الطالب مباشرة إلى واجهة الموقع مع معلمات النجاح
                $redirectTarget = $isWalletTopup ? 'https://codeshell.kesug.com/wallet.html?status=success&tx=' . $transaction->id : $frontendUrl . '?status=success&course_id=' . $transaction->course_id . '&tx=' . $transaction->id;
                return redirect($redirectTarget);

            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Payment Processing Failed: ' . $e->getMessage());
                if ($request->wantsJson() || $request->expectsJson()) {
                    return response()->json(['status' => false, 'message' => 'حدث خطأ أثناء معالجة الدفع.'], 500);
                }
                return redirect($frontendUrl . '?status=failed&message=' . urlencode('حدث خطأ أثناء معالجة الدفع.'));
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
            $isWalletTopup = ($transaction->course_id === null) || data_get($transaction->payload, 'is_wallet_topup', false);
            if ($isWalletTopup) {
                DB::transaction(function () use ($transaction, $result) {
                    $lockedTx = Transaction::whereKey($transaction->id)->lockForUpdate()->first();
                    if ($lockedTx && !$lockedTx->isCompleted()) {
                        $user = \App\Models\User::lockForUpdate()->find($lockedTx->user_id);
                        if ($user) {
                            $topupAmount = (float) $lockedTx->amount;
                            $user->wallet_balance = (float) ($user->wallet_balance ?? 0) + $topupAmount;
                            $user->save();

                            \App\Models\WalletTransaction::create([
                                'user_id'       => $user->id,
                                'type'          => 'credit',
                                'amount'        => $topupAmount,
                                'balance_after' => (float) $user->wallet_balance,
                                'reference_id'  => (string) $lockedTx->id,
                                'description'   => "شحن المحفظة عبر الـ Webhook (مرجع: {$lockedTx->gateway_transaction_id})",
                            ]);
                        }
                        $lockedTx->update([
                            'status'  => Transaction::STATUS_COMPLETED,
                            'paid_at' => now(),
                            'payload' => array_merge($lockedTx->payload ?? [], $result['raw_payload'] ?? []),
                        ]);
                    }
                });
            } else {
                $this->subscriptionService->activateFromTransaction($transaction, $result['raw_payload'] ?? []);
            }
        } else {
            $this->subscriptionService->markFailedFromTransaction($transaction, 'Webhook status: ' . $result['status']);
        }

        return response()->json(['status' => true]);
    }

    /**
     * ====================================================
     * الدفع المباشر ورسم الاشتراك في الكورس باستخدام رصيد المحفظة
     * المسار: POST /api/courses/{id}/pay-with-wallet
     * ====================================================
     */
    public function payWithWallet(Request $request, $courseId): JsonResponse
    {
        $user = $request->user();
        $course = Course::findOrFail($courseId);

        // 1. الكورسات المجانية لا تحتاج خصم من المحفظة
        if ($course->is_free || (float)$course->price === 0.0) {
            $freeSub = $this->subscriptionService->createFreeSubscription($user, $course);
            return response()->json([
                'status'        => true,
                'message'       => 'الكورس مجاني وتم تفعيل اشتراكك بنجاح!',
                'is_subscribed' => true,
            ]);
        }

        // 2. فحص ما إذا كان المستخدم مشتركاً بالفعل
        if ($course->isUserSubscribed($user->id)) {
            return response()->json([
                'status'             => false,
                'message'            => 'أنت مشترك بالفعل في هذا الكورس.',
                'already_subscribed' => true,
            ], 422);
        }

        // 3. حساب سعر الكورس بناءً على دولة المستخدم
        $prices = $course->prices ?? [];
        if (empty($prices)) {
            $prices = PricingService::defaults();
        }
        $pricing = PricingService::priceFor($user->country, $prices);
        $amount = (float) $pricing['price'];

        // 4. التحقق من كفاية رصيد المحفظة لدى المستخدم
        if ((float) $user->wallet_balance < $amount) {
            return response()->json([
                'status'          => false,
                'message'         => 'عذراً، رصيد المحفظة الحالي غير كافٍ للاشتراك. يرجى شحن المحفظة أولاً.',
                'wallet_balance'  => (float) $user->wallet_balance,
                'required_amount' => $amount,
            ], 400);
        }

        DB::beginTransaction();
        try {
            // 5. خصم المبلغ المالي من رصيد المحفظة
            $user->wallet_balance = (float) $user->wallet_balance - $amount;
            $user->save();

            // 6. تسجيل حركة المحفظة المخصومة
            \App\Models\WalletTransaction::create([
                'user_id'       => $user->id,
                'type'          => 'course_purchase',
                'amount'        => $amount,
                'balance_after' => (float) $user->wallet_balance,
                'reference_id'  => (string) $course->id,
                'description'   => "شراء كورس ({$course->title}) خصماً من رصيد المحفظة",
            ]);

            // 7. إنشاء المعاملة المالية المحلية
            $transaction = new Transaction([
                'user_id'         => $user->id,
                'course_id'       => $course->id,
                'amount'          => $amount,
                'currency_code'   => $pricing['currency_code'] ?? 'EGP',
                'payment_gateway' => 'wallet',
                'status'          => Transaction::STATUS_COMPLETED,
                'paid_at'         => now(),
            ]);
            $transaction->save();

            // 8. تفعيل الاشتراك وإلحاق الطالب بمجموعة كورس مفتوحة
            $this->subscriptionService->activateFromTransaction($transaction);

            DB::commit();

            return response()->json([
                'status'         => true,
                'message'        => 'تم الاشتراك في الكورس بنجاح وخصم المبلغ من محفظتك!',
                'is_subscribed'  => true,
                'wallet_balance' => (float) $user->wallet_balance,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Wallet Course Payment Failed: ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'فشلت عملية الاشتراك بالمحفظة: ' . $e->getMessage(),
            ], 500);
        }
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