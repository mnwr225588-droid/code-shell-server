<?php

/*
|--------------------------------------------------------------------------
| إعدادات نظام الدفع في منصة Code Shell
|--------------------------------------------------------------------------
|
| البنية معمارية محايدة للبوابة (Gateway-Agnostic):
|  - 'gateway' يحدد البوابة الفعّالة حالياً.
|  - 'sandbox' محاكي آمن يختبر الدورة كاملة دون بيانات بنكية حقيقية.
|  - لربط بوابة حقيقية (paymob/stripe/tap/...) أضف Driver جديداً في
|    app/Services/Payment/Gateways وأضف بياناته هنا، ثم غيّر قيمة 'gateway'.
|
| الأمان: السعر والعملة يُحسبان على السيرفر فقط (PricingService) حسب
| دولة المستخدم، ولا يُقبل أي سعر قادم من التطبيق إطلاقاً.
|
*/

return [

    /*
    | البوابة الفعّالة: sandbox | paymob | stripe | tap | paypal
    */
    'gateway' => env('PAYMENT_GATEWAY', 'sandbox'),

    /*
    | مسار العودة إلى التطبيق بعد انتهاء الدفع في صفحة البوابة
    | (يستخدمه العميل لمتابعة حالة المعاملة بعد redirect).
    */
    'return_url' => env('PAYMENT_RETURN_URL', ''),

    /*
    | الويب هوك: يستقبل إشعارات البوابة عند نجاح/فشل الدفع.
    | المسار الحقيقي: https://example.com/api/payment/webhook
    */
    'webhook_url' => env('PAYMENT_WEBHOOK_URL', ''),
    'webhook_path' => env('PAYMENT_WEBHOOK_PATH', '/api/payment/webhook'),

    /*
    | بيانات كل بوابة.
    */
    'sandbox' => [
        // سر توقيع الـ Webhook للمحاكي (HMAC-SHA256).
        'secret' => env('SANDBOX_WEBHOOK_SECRET', 'sandbox_demo_secret_change_me'),
        // مهلة صلاحية جلسة الدفع بالمحاكي (دقيقة).
        'session_ttl_minutes' => 30,
    ],

    'paymob' => [
        'api_key' => env('PAYMOB_API_KEY', ''),
        'integration_id' => env('PAYMOB_INTEGRATION_ID', ''),
        'iframes' => env('PAYMOB_IFRAME_ID', ''),
        'hmac_secret' => env('PAYMOB_HMAC_SECRET', ''),
        'mode' => env('PAYMOB_MODE', 'sandbox'),
    ],

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY', ''),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
        'mode' => env('STRIPE_MODE', 'sandbox'),
    ],

    'tap' => [
        'secret_key' => env('TAP_SECRET_KEY', ''),
        'mode' => env('TAP_MODE', 'sandbox'),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID', ''),
        'client_secret' => env('PAYPAL_CLIENT_SECRET', ''),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID', ''),
        'mode' => env('PAYPAL_MODE', 'sandbox'),
    ],

];
