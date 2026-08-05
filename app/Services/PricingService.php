<?php

namespace App\Services;

/**
 * نظام التسعير متعدد العملات الثابت في منصة Code Shell.
 *
 * الأسعار ثابتة ومحددة يدوياً لكل عملة دون أي اعتماد على أسعار صرف
 * خارجية. يُحفظ لكل كورس مصفوفة prices بالمفتاح (كود العملة) والقيمة (السعر).
 * عند الطلب يُحدد السعر حسب دولة المستخدم المسجلة في حسابه.
 */
class PricingService
{
    /** الأسعار الافتراضية لكل عملة عند إنشاء كورس جديد. */
    public const DEFAULT_PRICES = [
        'EGP' => 300,
        'SAR' => 40,
        'AED' => 40,
        'QAR' => 40,
        'OMR' => 30,
        'KWD' => 35,
        'BHD' => 35,
        'IQD' => 500,
        'JOD' => 40,
        'LBP' => 20,
        'SYP' => 20,
        'ILS' => 10,
        'YER' => 700,
        'LYD' => 40,
        'TND' => 40,
        'DZD' => 500,
        'MAD' => 100,
        'SDG' => 20,
    ];

    /** الرموز النصية للعملات (ج.م، ر.س، ₪ ...). */
    public const CURRENCY_SYMBOLS = [
        'EGP' => 'ج.م',
        'SAR' => 'ر.س',
        'AED' => 'د.إ',
        'QAR' => 'ر.ق',
        'OMR' => 'ر.ع',
        'KWD' => 'د.ك',
        'BHD' => 'د.ب',
        'IQD' => 'د.ع',
        'JOD' => 'د.أ',
        'LBP' => 'ل.ل',
        'SYP' => 'ل.س',
        'ILS' => '₪',
        'YER' => 'ر.ي',
        'LYD' => 'د.ل',
        'TND' => 'د.ت',
        'DZD' => 'د.ج',
        'MAD' => 'د.م',
        'SDG' => 'ج.س',
    ];

    /** مطابقة اسم الدولة (المخزن في users.country) مع كود العملة. */
    public const COUNTRY_CURRENCY = [
        // الأسماء العربية كما تُرسل من التطبيق
        'مصر' => 'EGP',
        'المملكة العربية السعودية' => 'SAR',
        'الإمارات العربية المتحدة' => 'AED',
        'قطر' => 'QAR',
        'سلطنة عمان' => 'OMR',
        'الكويت' => 'KWD',
        'البحرين' => 'BHD',
        'العراق' => 'IQD',
        'الأردن' => 'JOD',
        'لبنان' => 'LBP',
        'سوريا' => 'SYP',
        'فلسطين' => 'ILS',
        'اليمن' => 'YER',
        'ليبيا' => 'LYD',
        'تونس' => 'TND',
        'الجزائر' => 'DZD',
        'المغرب' => 'MAD',
        'السودان' => 'SDG',
        // أسماء إنجليزية شائعة
        'Egypt' => 'EGP',
        'Saudi Arabia' => 'SAR',
        'United Arab Emirates' => 'AED',
        'UAE' => 'AED',
        'Qatar' => 'QAR',
        'Oman' => 'OMR',
        'Kuwait' => 'KWD',
        'Bahrain' => 'BHD',
        'Iraq' => 'IQD',
        'Jordan' => 'JOD',
        'Lebanon' => 'LBP',
        'Syria' => 'SYP',
        'Palestine' => 'ILS',
        'Yemen' => 'YER',
        'Libya' => 'LYD',
        'Tunisia' => 'TND',
        'Algeria' => 'DZD',
        'Morocco' => 'MAD',
        'Sudan' => 'SDG',
        // رموز الهاتف
        '+20' => 'EGP',
        '+966' => 'SAR',
        '+971' => 'AED',
        '+974' => 'QAR',
        '+968' => 'OMR',
        '+965' => 'KWD',
        '+973' => 'BHD',
        '+964' => 'IQD',
        '+962' => 'JOD',
        '+961' => 'LBP',
        '+963' => 'SYP',
        '+970' => 'ILS',
        '+967' => 'YER',
        '+218' => 'LYD',
        '+216' => 'TND',
        '+213' => 'DZD',
        '+212' => 'MAD',
        '+249' => 'SDG',
    ];

    /** العملة الافتراضية عند عدم وجود دولة أو دولة غير مدرجة. */
    public const DEFAULT_CURRENCY = 'EGP';

    /** إرجاع الأسعار الافتراضية كاملة (18 عملة). */
    public static function defaults(): array
    {
        return self::DEFAULT_PRICES;
    }

    /** رمز العملة النصي (ج.م، ر.س، ...). */
    public static function symbol(string $currencyCode): string
    {
        return self::CURRENCY_SYMBOLS[$currencyCode] ?? self::CURRENCY_SYMBOLS[self::DEFAULT_CURRENCY];
    }

    /** تحديد كود العملة حسب دولة المستخدم (افتراضياً EGP). */
    public static function currencyForCountry(?string $country): string
    {
        if ($country !== null && $country !== '') {
            $trimmed = trim($country);
            if (isset(self::COUNTRY_CURRENCY[$trimmed])) {
                return self::COUNTRY_CURRENCY[$trimmed];
            }
        }
        return self::DEFAULT_CURRENCY;
    }

    /**
     * حساب السعر المناسب لدولة المستخدم من مصفوفة الأسعار.
     *
     * @return array{price: float, currency_code: string, currency_symbol: string}
     */
    public static function priceFor(?string $country, array $prices): array
    {
        $code = self::currencyForCountry($country);
        $price = $prices[$code] ?? self::DEFAULT_PRICES[self::DEFAULT_CURRENCY];

        return [
            'price' => (float) $price,
            'currency_code' => $code,
            'currency_symbol' => self::symbol($code),
        ];
    }

    /**
     * تطبيع مصفوفة الأسعار القادمة من الأدمن:
     * - يقبل مصفوفة أو نص JSON.
     * - يتجاهل القيم الفارغة.
     * - يدمج مع القيم الافتراضية لضمان اكتمال كل العملات.
     */
    public static function normalizePrices(mixed $prices): array
    {
        $parsed = $prices;
        if (is_string($prices)) {
            $decoded = json_decode($prices, true);
            $parsed = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($parsed)) {
            $parsed = [];
        }

        $clean = [];
        foreach ($parsed as $code => $value) {
            $code = strtoupper((string) $code);
            if ($value === null || $value === '') {
                continue;
            }
            $clean[$code] = (float) $value;
        }

        return array_merge(self::defaults(), $clean);
    }
}
