<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CouponTest extends TestCase
{
    private function baseCoupon(): array
    {
        return [
            'code'           => 'TEST',
            'is_active'      => 1,
            'valid_from'     => null,
            'valid_until'    => null,
            'min_subtotal'   => 0.0,
            'max_uses'       => null,
            'times_used'     => 0,
            'discount_type'  => 'percent',
            'discount_value' => 10.0,
        ];
    }

    public function testPercentCouponDiscount(): void
    {
        $r = validateCouponPure($this->baseCoupon(), 1000.0);
        $this->assertTrue($r['ok']);
        $this->assertSame(100.0, $r['discount']);
    }

    public function testFixedCouponCappedAtSubtotal(): void
    {
        $c = $this->baseCoupon();
        $c['discount_type'] = 'fixed';
        $c['discount_value'] = 500.0;

        $r = validateCouponPure($c, 200.0);
        $this->assertTrue($r['ok']);
        $this->assertSame(200.0, $r['discount']);
    }

    public function testInactiveCouponRejected(): void
    {
        $c = $this->baseCoupon();
        $c['is_active'] = 0;
        $r = validateCouponPure($c, 1000.0);
        $this->assertFalse($r['ok']);
        $this->assertSame('Coupon inactive', $r['message']);
    }

    public function testExpiredCouponRejected(): void
    {
        $c = $this->baseCoupon();
        $c['valid_until'] = '2000-01-01 00:00:00';
        $r = validateCouponPure($c, 1000.0);
        $this->assertFalse($r['ok']);
        $this->assertSame('Expired', $r['message']);
    }

    public function testNotYetValidCouponRejected(): void
    {
        $c = $this->baseCoupon();
        $c['valid_from'] = '2099-01-01 00:00:00';
        $r = validateCouponPure($c, 1000.0);
        $this->assertFalse($r['ok']);
        $this->assertSame('Not yet valid', $r['message']);
    }

    public function testBelowMinimumSubtotalRejected(): void
    {
        $c = $this->baseCoupon();
        $c['min_subtotal'] = 500.0;
        $r = validateCouponPure($c, 200.0);
        $this->assertFalse($r['ok']);
        $this->assertSame('Below minimum', $r['message']);
    }

    public function testMaxUsesExhausted(): void
    {
        $c = $this->baseCoupon();
        $c['max_uses'] = 5;
        $c['times_used'] = 5;
        $r = validateCouponPure($c, 1000.0);
        $this->assertFalse($r['ok']);
        $this->assertSame('Coupon used up', $r['message']);
    }
}
