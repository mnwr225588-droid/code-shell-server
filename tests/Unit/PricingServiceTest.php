<?php

namespace Tests\Unit;

use App\Models\CoursePlan;
use App\Services\PricingService;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    public function test_plan_saved_price_is_used_for_its_currency_even_if_price_map_is_stale(): void
    {
        $plan = new CoursePlan([
            'currency' => 'EGP',
            'price' => 600,
            'prices' => ['EGP' => 300],
        ]);

        $pricing = PricingService::priceForPlan('مصر', $plan);

        $this->assertSame(600.0, $pricing['price']);
        $this->assertSame('EGP', $pricing['currency_code']);
    }

    public function test_plan_discount_price_is_used_for_its_currency(): void
    {
        $plan = new CoursePlan([
            'currency' => 'EGP',
            'price' => 600,
            'original_price' => 600,
            'discount_price' => 450,
            'has_discount' => true,
        ]);

        $pricing = PricingService::priceForPlan('مصر', $plan);

        $this->assertSame(450.0, $pricing['price']);
    }

    public function test_plan_uses_configured_price_for_a_different_currency(): void
    {
        $plan = new CoursePlan([
            'currency' => 'EGP',
            'price' => 600,
            'prices' => ['SAR' => 75],
        ]);

        $pricing = PricingService::priceForPlan('المملكة العربية السعودية', $plan);

        $this->assertSame(75.0, $pricing['price']);
        $this->assertSame('SAR', $pricing['currency_code']);
    }
}
