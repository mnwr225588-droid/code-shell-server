<?php

/**
 * ====================================================
 * اسم الملف: EasyKashGateway.php
 * المسار: app/Services/Payment/Gateways/EasyKashGateway.php
 * 
 * الوصف والمهمة الرئيسية:
 * هذا الملف يمثل مشغل بوابة EasyKash (Driver) متوافقًا مع معمارية PaymentGatewayInterface.
 * يربط بين PaymentGatewayManager و EasyKashService لتقديم واجهة موحدة لبوابة الدفع.
 * 
 * الوظائف التفصيلية:
 * 1. name: إرجاع اسم البوابة الفريد ('easykash').
 * 2. createPayment: إنشاء عملية دفع جديدة لدى EasyKash وترجيع رابط الدفع ومعرف المعاملة.
 * 3. handleWebhook: استقبال ومعالجة إشعارات الـ Webhook أو الـ Callback وتوقيعاتها.
 * ====================================================
 */

namespace App\Services\Payment\Gateways;

use App\Models\Transaction;
use App\Services\EasyKashService;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Http\Request;
use RuntimeException;

class EasyKashGateway implements PaymentGatewayInterface
{
    /** كائن خدمة التواصل مع EasyKash */
    protected EasyKashService $easyKashService;

    /**
     * التهيئة وتوليد الخدمة
     */
    public function __construct()
    {
        $this->easyKashService = new EasyKashService();
    }

    /**
     * اسم البوابة الفعالة المسجل في جدول transactions
     */
    public function name(): string
    {
        return 'easykash';
    }

    /**
     * إنشاء جلسة دفع جديدة لدى بوابة EasyKash
     *
     * @param Transaction $transaction المعاملة المالية المحلية
     * @param string $returnUrl رابط العودة بعد الدفع
     * @return array{payment_url: string, gateway_transaction_id: string, payload: array}
     */
    public function createPayment(Transaction $transaction, string $returnUrl): array
    {
        return $this->easyKashService->createPaymentUrl($transaction, $returnUrl);
    }

    /**
     * استقبال ومعالجة إشعارات البوابة والتحقق من التوقيع الرقمي
     *
     * @param Request $request طلب الـ HTTP القادم من البوابة
     * @return array{gateway_transaction_id: string, status: string, raw_payload: array}
     * @throws RuntimeException عند فشل التحقق من التوقيع أو غياب معرف المعاملة
     */
    public function handleWebhook(Request $request): array
    {
        $params = $request->all();
        $signature = $request->header('X-Signature') ?: ($params['signature'] ?? $params['hmac'] ?? null);

        // التحقق من صحة توقيع الطلب
        if (!$this->easyKashService->validateSignature($params, $signature)) {
            throw new RuntimeException('توقيع EasyKash غير صالح أو تم التلاعب بالطلب.');
        }

        $gatewayTxId = (string) ($params['gateway_transaction_id'] ?? $params['transaction_id'] ?? $params['merchant_order_id'] ?? '');

        if (empty($gatewayTxId)) {
            throw new RuntimeException('معرف معاملة EasyKash مفقود.');
        }

        $statusRaw = strtolower((string) ($params['status'] ?? 'pending'));

        // مطابقة حالة العملية
        $status = match ($statusRaw) {
            'paid', 'completed', 'success', 'successful', 'true' => Transaction::STATUS_COMPLETED,
            'failed', 'declined', 'error'                         => Transaction::STATUS_FAILED,
            'cancelled', 'canceled'                             => Transaction::STATUS_CANCELLED,
            default                                             => Transaction::STATUS_PENDING,
        };

        return [
            'gateway_transaction_id' => $gatewayTxId,
            'status'                 => $status,
            'raw_payload'            => $params,
        ];
    }
}
