<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// The real voucher rules (pure functions only; the DB helpers are never called).
require_once __DIR__ . '/../includes/vouchers.php';

final class VoucherTest extends TestCase
{
    private function voucher(array $over = []): array
    {
        return $over + [
            'wallet_id'      => 1,
            'kind'           => 'discount',
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

    public function testDiscountVoucherComesOffTheItems(): void
    {
        $r = couponCheck($this->voucher(), 1000.0, 120.0);
        $this->assertTrue($r['ok']);
        $this->assertSame(100.0, $r['discount']);
    }

    public function testFixedDiscountCappedAtSubtotal(): void
    {
        $r = couponCheck($this->voucher(['discount_type' => 'fixed', 'discount_value' => 500.0]), 200.0);
        $this->assertSame(200.0, $r['discount']);
    }

    public function testFreeShippingTakesTheWholeFee(): void
    {
        $v = $this->voucher(['kind' => 'shipping', 'discount_value' => 100.0]);
        $this->assertSame(120.0, couponCheck($v, 500.0, 120.0)['discount']);
    }

    public function testFixedShippingVoucherCappedAtFee(): void
    {
        $v = $this->voucher(['kind' => 'shipping', 'discount_type' => 'fixed', 'discount_value' => 200.0]);
        $this->assertSame(85.0, couponCheck($v, 500.0, 85.0)['discount']);
    }

    public function testShippingVoucherLockedForPickup(): void
    {
        $v = $this->voucher(['kind' => 'shipping', 'discount_value' => 100.0]);
        $this->assertFalse(couponCheck($v, 500.0, 0.0)['ok']);
    }

    public function testBelowMinimumReportsHowMuchMore(): void
    {
        $r = couponCheck($this->voucher(['min_subtotal' => 1000.0]), 850.0);
        $this->assertFalse($r['ok']);
        $this->assertSame(150.0, $r['short']);
    }

    public function testExpiredInactiveAndUsedUpRejected(): void
    {
        $this->assertFalse(couponCheck($this->voucher(['valid_until' => '2000-01-01 00:00:00']), 1000.0)['ok']);
        $this->assertFalse(couponCheck($this->voucher(['is_active' => 0]), 1000.0)['ok']);
        $this->assertFalse(couponCheck($this->voucher(['max_uses' => 5, 'times_used' => 5]), 1000.0)['ok']);
        $this->assertFalse(couponCheck($this->voucher(['valid_from' => '2999-01-01 00:00:00']), 1000.0)['ok']);
    }

    public function testBestPickTakesBiggestSavingPerKind(): void
    {
        $wallet = [
            $this->voucher(['wallet_id' => 1, 'discount_value' => 10.0]),                                       // ₱100
            $this->voucher(['wallet_id' => 2, 'discount_type' => 'fixed', 'discount_value' => 150.0]),          // ₱150
            $this->voucher(['wallet_id' => 3, 'discount_value' => 40.0, 'min_subtotal' => 2000.0]),            // locked
            $this->voucher(['wallet_id' => 4, 'kind' => 'shipping', 'discount_value' => 100.0]),
        ];
        $this->assertSame(2, voucherBestPick($wallet, 'discount', 1000.0, 120.0));
        $this->assertSame(4, voucherBestPick($wallet, 'shipping', 1000.0, 120.0));
        $this->assertSame(0, voucherBestPick($wallet, 'shipping', 1000.0, 0.0));
    }

    public function testBestPickTieGoesToSoonestExpiry(): void
    {
        $wallet = [
            $this->voucher(['wallet_id' => 1, 'valid_until' => '2999-12-31 00:00:00']),
            $this->voucher(['wallet_id' => 2, 'valid_until' => '2999-01-31 00:00:00']),
            $this->voucher(['wallet_id' => 3]),
        ];
        $this->assertSame(2, voucherBestPick($wallet, 'discount', 1000.0, 0.0));
    }

    public function testLabels(): void
    {
        $this->assertSame('10% off', voucherLabel($this->voucher()));
        $this->assertSame('₱50 off', voucherLabel($this->voucher(['discount_type' => 'fixed', 'discount_value' => 50.0])));
        $this->assertSame('Free shipping', voucherLabel($this->voucher(['kind' => 'shipping', 'discount_value' => 100.0])));
        $this->assertSame('50% off shipping', voucherLabel($this->voucher(['kind' => 'shipping', 'discount_value' => 50.0])));
    }
}
