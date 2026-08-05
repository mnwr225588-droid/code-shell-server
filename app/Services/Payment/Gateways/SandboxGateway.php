<?php

namespace App\Services\Payment\Gateways;

use App\Models\Transaction;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * محاكي بوابة دفع آمن للاختبار والتجربة الكاملة محلياً.
 *
 * يفتح صفحة دفع محلية (تشبه صفحة البوابة الحقيقية) تعرض المبلغ والعملة
 * مع زرّي "إتمام الدفع" و"إلغاء الدفع"، وترسل النتيجة إلى نفس الـ Webhook
 * الذي تستخدمه البوابات الحقيقية مع توقيع HMAC-SHA256 صالح، فيتم اختبار
 * الدورة الكاملة (دفع → Webhook → تفعيل اشتراك) دون أي بيانات بنكية.
 *
 * لربط بوابة حقيقية لاحقاً: أنشئ Gateway جديداً في نفس المجلد ينفّذ
 * PaymentGatewayInterface، وسجّله في PaymentGatewayManager، وبدّل
 * PAYMENT_GATEWAY في .env.
 */
class SandboxGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'sandbox';
    }

    public function createPayment(Transaction $transaction, string $returnUrl): array
    {
        $ref = 'sandbox_' . bin2hex(random_bytes(10));

        $payload = [
            'ref' => $ref,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency_code,
            'course_title' => $transaction->course?->title,
            'return_url' => $returnUrl,
            'expires_at' => now()->addMinutes(
                (int) config('payment.sandbox.session_ttl_minutes', 30)
            )->toIso8601String(),
        ];

        return [
            'payment_url' => route('payment.sandbox.page', ['ref' => $ref]),
            'gateway_transaction_id' => $ref,
            'payload' => $payload,
        ];
    }

    public function handleWebhook(Request $request): array
    {
        $body = $request->getContent();
        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new RuntimeException('Sandbox webhook: payload غير صالح.');
        }

        $this->verifySignature($data, $body);

        $ref = $data['gateway_transaction_id'] ?? null;
        $status = $data['status'] ?? null;

        if (!is_string($ref) || !in_array($status, ['completed', 'failed', 'refunded'], true)) {
            throw new RuntimeException('Sandbox webhook: بيانات ناقصة.');
        }

        return [
            'gateway_transaction_id' => $ref,
            'status' => $status,
            'raw_payload' => $data,
        ];
    }

    /**
     * التحقق من توقيع HMAC-SHA256 لحماية الـ Webhook من الطلبات الوهمية.
     *
     * التوقيع يُحسب على تمثيل JSON للبيانات (بدون حقل signature) بترميز
     * ثابت (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) — نفس الترميز
     * الذي استُخدم عند إنشاء التوقيع في صفحة الدفع، وهو أسلوب موثوق
     * تستخدمه بوابات حقيقية عدة.
     */
    protected function verifySignature(array $data, string $rawBody): void
    {
        $provided = $data['signature'] ?? null;
        $secret = (string) config('payment.sandbox.secret');

        if (!is_string($provided) || $secret === '') {
            throw new RuntimeException('Sandbox webhook: توقيع مفقود.');
        }

        unset($data['signature']);

        $expected = hash_hmac('sha256', $this->canonicalJson($data), $secret);

        if (!hash_equals($expected, $provided)) {
            throw new RuntimeException('Sandbox webhook: توقيع غير صالح.');
        }
    }

    /**
     * تمثيل JSON قياسي ومحدد للترتيب (ترتيب المفاتيح يبقى كما أُرسل)
     * يُستخدم لحساب التوقيع والتحقق منه بنفس البايتات تماماً.
     */
    protected function canonicalJson(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * إنشاء رابط صفحة الدفع المزيفة (تستخدمها صفحة Sandbox فقط لبناء
     * الزرّين، ولا تُستخدم في أي مسار أمان آخر).
     */
    public static function webhookUrl(): string
    {
        return URL::to(config('payment.webhook_path', '/api/payment/webhook'));
    }
}
