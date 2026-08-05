<?php

namespace App\Services\Payment;

use App\Models\Transaction;
use Illuminate\Http\Request;

/**
 * عقد موحد لكل بوابات الدفع.
 *
 * أي بوابة حقيقية (Paymob / Stripe / Tap / PayPal ...) تُنفّذ هذا
 * العقد في app/Services/Payment/Gateways، ثم تُسجّل في
 * PaymentGatewayManager ليتم تفعيلها من config/payment.php فقط.
 */
interface PaymentGatewayInterface
{
    /**
     * اسم البوابة (يُخزن في عمود transactions.payment_gateway).
     */
    public function name(): string;

    /**
     * إنشاء عملية دفع لدى البوابة بالمبلغ والعملة الصحيحين.
     *
     * ملاحظة أمنية: المبلغ والعملة يأتيان من الـ Transaction التي
     * أنشأها السيرفر حصرياً (دون أي إدخال من العميل).
     *
     * @return array{payment_url: string, gateway_transaction_id: string, payload: array}
     */
    public function createPayment(Transaction $transaction, string $returnUrl): array;

    /**
     * استقبال إشعار البوابة (Webhook) والتحقق من صحة توقيعه.
     *
     * @return array{gateway_transaction_id: string, status: 'completed'|'failed'|'refunded', raw_payload: array}
     *
     * @throws \RuntimeException عند فشل التحقق من التوقيع أو استجابة غير صالحة
     */
    public function handleWebhook(Request $request): array;
}
