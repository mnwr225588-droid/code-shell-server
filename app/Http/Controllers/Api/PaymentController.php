<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Transaction;
use App\Services\Payment\Gateways\SandboxGateway;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentController extends Controller
{
    /**
     * بدء عملية الدفع: POST /api/courses/{id}/pay
     *
     * الأمان: لا يُقبل أي سعر أو عملة من التطبيق إطلاقاً. السعر والعملة
     * يُحسبان حصرياً على السيرفر من دولة المستخدم المسجلة ومصفوفة
     * أسعار الكورس الثابتة (PricingService).
     */
    public function initiate(Request $request, $courseId): JsonResponse
    {
        $user = $request->user();
        $course = Course::findOrFail($courseId);

        if ($course->is_free) {
            return response()->json([
                'status' => false,
                'message' => 'هذا الكورس مجاني وليس بحاجة إلى دفع.',
            ], 422);
        }

        if ($course->isUserSubscribed($user->id)) {
            return response()->json([
                'status' => false,
                'message' => 'أنت مشترك بالفعل في هذا الكورس.',
                'already_subscribed' => true,
            ], 422);
        }

        // السعر والعملة من السيرفر فقط.
        $prices = $course->prices ?? [];
        if (empty($prices)) {
            $prices = PricingService::defaults();
        }
        $pricing = PricingService::priceFor($user->country, $prices);
        $amount = $pricing['price'];
        $currency = $pricing['currency_code'];

        $gateway = PaymentGatewayManager::active();

        // استئناف معاملة معلقة لنفس المستخدم والكورس (بدون إنشاء جديدة)
        // لمنع تراكم جلسات الدفع المكررة.
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
                    'status' => true,
                    'message' => 'استئناف جلسة دفع سابقة.',
                    'data' => [
                        'transaction_id' => $pending->id,
                        'payment_url' => $url,
                        'amount' => (float) $pending->amount,
                        'currency_code' => $pending->currency_code,
                        'currency_symbol' => PricingService::symbol($pending->currency_code),
                    ],
                ]);
            }
        }

        // معاملة جديدة.
        $transaction = new Transaction([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'amount' => $amount,
            'currency_code' => $currency,
            'payment_gateway' => $gateway->name(),
            'status' => Transaction::STATUS_PENDING,
        ]);
        $transaction->save();

        try {
            $created = $gateway->createPayment($transaction, (string) config('payment.return_url'));
        } catch (\Throwable $e) {
            $transaction->update([
                'status' => Transaction::STATUS_FAILED,
                'payload' => ['error' => $e->getMessage()],
            ]);
            throw $e;
        }

        $transaction->update([
            'gateway_transaction_id' => $created['gateway_transaction_id'],
            'payload' => $created['payload'],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم تجهيز جلسة الدفع بنجاح.',
            'data' => [
                'transaction_id' => $transaction->id,
                'payment_url' => $created['payment_url'],
                'amount' => (float) $transaction->amount,
                'currency_code' => $transaction->currency_code,
                'currency_symbol' => PricingService::symbol($transaction->currency_code),
            ],
        ]);
    }

    /**
     * استقبال إشعارات البوابة: POST /api/payment/webhook
     *
     * - تحقق من التوقيع (كل بوابة تحقق بطريقتها عبر الـ Driver).
     * - منع التكرار: فهرس فريد على gateway_transaction_id + فحص الحالة
     *   داخل قفل الصف (lockForUpdate).
     * - تفعيل الاشتراك والمعاملة في DB::transaction واحدة.
     */
    public function webhook(Request $request): JsonResponse
    {
        $gateway = PaymentGatewayManager::active();

        try {
            $result = $gateway->handleWebhook($request);
        } catch (RuntimeException $e) {
            // توقيع غير صالح أو بيانات غير مفهومة → لا نُكمل أبداً.
            return response()->json([
                'status' => false,
                'message' => 'Webhook غير صالح: ' . $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'خطأ في معالجة الـ Webhook.',
            ], 500);
        }

        $transaction = Transaction::where(
            'gateway_transaction_id',
            $result['gateway_transaction_id']
        )->first();

        if (!$transaction) {
            return response()->json([
                'status' => false,
                'message' => 'معاملة غير معروفة.',
            ], 404);
        }

        DB::transaction(function () use ($transaction, $result) {
            // قفل الصف لمنع سباق التحديثات إذا وصل إشعاران معاً.
            $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->first();

            // Idempotency: إذا اكتملت المعاملة سابقاً نرجع نجاحاً دون تكرار أي أثر.
            if ($locked->isCompleted()) {
                return;
            }

            $locked->update([
                'status' => $result['status'],
                'payload' => $result['raw_payload'],
            ]);

            if ($result['status'] === Transaction::STATUS_COMPLETED) {
                // تفعيل الاشتراك بشكل دائم وفوري.
                $locked->user->subscribedCourses()->syncWithoutDetaching([$locked->course_id]);
            }
        });

        return response()->json(['status' => true]);
    }

    /**
     * حالة آخر معاملة للمستخدم على كورس معين: GET /api/courses/{id}/payment-status
     * (يستخدمها التطبيق بعد عودة المستخدم من صفحة البوابة).
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
            'data' => [
                'is_subscribed' => $isSubscribed,
                'transaction_status' => $transaction?->status,
                'amount' => $transaction ? (float) $transaction->amount : null,
                'currency_code' => $transaction?->currency_code,
                'currency_symbol' => $transaction ? PricingService::symbol($transaction->currency_code) : null,
            ],
        ]);
    }

    /**
     * صفحة الدفع التجريبية لمحاكي Sandbox (GET /api/payment/sandbox/{ref}).
     * تعرض المبلغ والعملة وزرّي نجاح/إلغاء، وتُرسل النتيجة للـ Webhook
     * بتوقيع صالح مسبقاً تماماً كما تفعل البوابة الحقيقية.
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

        // نبني نص الإشعار كاملاً ونوقّعه سيرفراً، ثم ترسله الصفحة كما هو.
        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        $completedPayload = json_encode([
            'gateway_transaction_id' => $ref,
            'status' => 'completed',
            'event' => 'payment.completed',
        ], $jsonFlags);

        $failedPayload = json_encode([
            'gateway_transaction_id' => $ref,
            'status' => 'failed',
            'event' => 'payment.failed',
        ], $jsonFlags);

        $completedSigned = json_encode([
            'gateway_transaction_id' => $ref,
            'status' => 'completed',
            'event' => 'payment.completed',
            'signature' => hash_hmac('sha256', $completedPayload, $secret),
        ], $jsonFlags);

        $failedSigned = json_encode([
            'gateway_transaction_id' => $ref,
            'status' => 'failed',
            'event' => 'payment.failed',
            'signature' => hash_hmac('sha256', $failedPayload, $secret),
        ], $jsonFlags);

        return response(
            view('payment.sandbox', compact(
                'courseTitle',
                'amount',
                'symbol',
                'webhookUrl',
                'completedSigned',
                'failedSigned',
            ))
        );
    }
}
