<?php

namespace App\Services\Payment;

use App\Services\Payment\Gateways\SandboxGateway;
use RuntimeException;

/**
 * مدير البوابات: يختار الـ Driver المناسب حسب config/payment.php.
 * عند ربط بوابة حقيقية لاحقاً، سجّل Driver الجديد في المصفوفة التالية.
 */
class PaymentGatewayManager
{
    /** @var array<string, class-string<PaymentGatewayInterface>> */
    private const GATEWAYS = [
        'sandbox' => SandboxGateway::class,
        // 'paymob' => \App\Services\Payment\Gateways\PaymobGateway::class,
        // 'stripe' => \App\Services\Payment\Gateways\StripeGateway::class,
        // 'tap'    => \App\Services\Payment\Gateways\TapGateway::class,
        // 'paypal' => \App\Services\Payment\Gateways\PaypalGateway::class,
    ];

    public static function active(): PaymentGatewayInterface
    {
        $gateway = (string) config('payment.gateway', 'sandbox');

        if (!isset(self::GATEWAYS[$gateway])) {
            throw new RuntimeException("بوابة الدفع غير معروفة: {$gateway}");
        }

        $class = self::GATEWAYS[$gateway];

        return new $class();
    }

    public static function isAvailable(string $gateway): bool
    {
        return isset(self::GATEWAYS[$gateway]);
    }
}
