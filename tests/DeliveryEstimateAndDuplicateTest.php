<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/delivery-zones.php';
require_once __DIR__ . '/../includes/duplicate-order.php';

final class DeliveryEstimateAndDuplicateTest extends TestCase
{
    protected function setUp(): void
    {
        date_default_timezone_set('Asia/Manila');
    }

    public function testSignatureIgnoresLineOrderAndMergesSplitLines(): void
    {
        $a = [['id' => 5, 'color' => 'Black', 'size' => 'M', 'quantity' => 2], ['id' => 9, 'quantity' => 1]];
        $b = [['id' => 9, 'quantity' => 1], ['id' => 5, 'color' => 'Black', 'size' => 'M', 'quantity' => 1],
              ['id' => 5, 'color' => 'Black', 'size' => 'M', 'quantity' => 1]];
        $this->assertSame(orderItemsSignature($a), orderItemsSignature($b));
    }

    public function testSignatureMatchesOrderItemRowsByProductIdNotLineId(): void
    {
        $cart = [['id' => 5, 'color' => 'Black', 'size' => 'M', 'quantity' => 2]];
        $row  = [['id' => 812, 'product_id' => 5, 'color' => 'Black', 'size' => 'M', 'quantity' => '2']];
        $this->assertSame(orderItemsSignature($cart), orderItemsSignature($row));
    }

    public function testSignatureTellsDifferentCartsApart(): void
    {
        $base = [['id' => 5, 'color' => 'Black', 'size' => 'M', 'quantity' => 2]];
        $this->assertNotSame(orderItemsSignature($base), orderItemsSignature([['id' => 5, 'color' => 'Black', 'size' => 'L', 'quantity' => 2]]));
        $this->assertNotSame(orderItemsSignature($base), orderItemsSignature([['id' => 5, 'color' => 'Black', 'size' => 'M', 'quantity' => 3]]));
    }

    public function testEstimateSkipsSundays(): void
    {
        $saturday = strtotime('2026-10-24 10:00');
        $e = estimatedDelivery('rizal', 'delivery', $saturday);     // 1-2 working days
        $this->assertSame('2026-10-26', date('Y-m-d', $e['from']));  // Sunday skipped
        $this->assertSame('2026-10-27', date('Y-m-d', $e['to']));
        $this->assertSame('Oct 26 – Oct 27', $e['label']);
        $this->assertSame('Oct 26', estimatedDelivery('', 'pickup', $saturday)['label']);
    }

    public function testUnknownZoneFallsBackToDefault(): void
    {
        $t = strtotime('2026-10-21 10:00');
        $this->assertSame(estimatedDelivery(DELIVERY_ZONE_DEFAULT, 'delivery', $t), estimatedDelivery('nowhere', 'delivery', $t));
    }

    public function testOrderZoneComesFromFullFeeThenAddress(): void
    {
        $this->assertSame('ncr', deliveryZoneOfOrder(100.0, 'Cebu City'));        // fee wins
        $this->assertSame('mindanao', deliveryZoneOfOrder(60.0, 'Davao City'));    // unknown fee: address
        $this->assertNull(deliveryZoneOfOrder(60.0, 'somewhere'));
    }

    public function testOrderEstimateText(): void
    {
        $order = ['status' => 'pending', 'created_at' => '2026-10-24 10:00:00',
                  'delivery_fee' => 50.0, 'shipping_discount' => 50.0, 'delivery_address' => 'X'];   // NCR fee fully vouchered
        $this->assertSame('Estimated delivery: Oct 26 – Oct 27', orderDeliveryEstimate($order));
        $this->assertSame('Ready for pickup by Oct 26', orderDeliveryEstimate(['delivery_address' => 'Store Pickup'] + $order));
        $this->assertNull(orderDeliveryEstimate(['status' => 'cancelled'] + $order));
    }
}
