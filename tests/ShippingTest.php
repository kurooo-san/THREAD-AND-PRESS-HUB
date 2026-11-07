<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/delivery-zones.php';
require_once __DIR__ . '/../includes/couriers.php';

final class ShippingTest extends TestCase
{
    public function testRateValidation(): void
    {
        $this->assertNull(deliveryRateError('75.50', '1', '3'));
        $this->assertNull(deliveryRateError('0', '1', '1'));
        $this->assertNotNull(deliveryRateError('-1', '1', '2'));
        $this->assertNotNull(deliveryRateError('abc', '1', '2'));
        $this->assertNotNull(deliveryRateError('1e999', '1', '2'));   // INF
        $this->assertNotNull(deliveryRateError('50', '3', '2'));       // from > to
        $this->assertNotNull(deliveryRateError('50', '0', '2'));
        $this->assertNotNull(deliveryRateError('50', '1.5', '2'));
        $this->assertNotNull(deliveryRateError('50', '1', '31'));
    }

    public function testFeeFromText(): void
    {
        $this->assertStringStartsWith('Shipping fee by area', deliveryFeeFromText());
    }

    public function testCourierNeedsTrackingNumber(): void
    {
        [$ok, $err] = shipmentFromInput(['courier' => 'jnt', 'tracking_number' => ' 820012345678 ', 'rider_name' => 'X', 'rider_phone' => '09171234567']);
        $this->assertNull($err);
        $this->assertSame(['courier' => 'jnt', 'tracking_number' => '820012345678', 'rider_name' => null, 'rider_phone' => null], $ok);

        $this->assertNotNull(shipmentFromInput(['courier' => 'jnt', 'tracking_number' => ''])[1]);
        $this->assertNotNull(shipmentFromInput(['courier' => 'fedex', 'tracking_number' => '12345'])[1]);
        $this->assertNotNull(shipmentFromInput(['courier' => 'lbc', 'tracking_number' => '<script>'])[1]);
        $this->assertNotNull(shipmentFromInput(['courier' => 'lalamove', 'tracking_number' => 'javascript:alert(1)'])[1]);
        $this->assertNotNull(shipmentFromInput(['courier' => 'lalamove', 'tracking_number' => 'http://share.lalamove.com/x'])[1]);
    }

    public function testOwnRiderNeedsNameAndPhone(): void
    {
        [$ok, $err] = shipmentFromInput(['courier' => 'rider', 'tracking_number' => '', 'rider_name' => '  Juan   Dela Cruz ', 'rider_phone' => '0917 123-4567']);
        $this->assertNull($err);
        $this->assertSame(['courier' => 'rider', 'tracking_number' => null, 'rider_name' => 'Juan Dela Cruz', 'rider_phone' => '09171234567'], $ok);

        $this->assertNotNull(shipmentFromInput(['courier' => 'rider', 'rider_name' => '', 'rider_phone' => '09171234567'])[1]);
        $this->assertNotNull(shipmentFromInput(['courier' => 'rider', 'rider_name' => 'Juan', 'rider_phone' => '12345'])[1]);
        $this->assertNotNull(shipmentFromInput(['courier' => 'rider', 'rider_name' => '<b>Juan</b>', 'rider_phone' => '09171234567'])[1]);
    }

    public function testGeneratedTrackingNumber(): void
    {
        $this->assertSame('TP-00000123', generatedTrackingNumber(123));
        $this->assertSame('TP-123456789', generatedTrackingNumber(123456789));   // longer ids still fit

        [$ok, $err] = shipmentFromInput(['courier' => 'jnt', 'tracking_number' => generatedTrackingNumber(123)]);
        $this->assertNull($err);
        $this->assertSame('TP-00000123', $ok['tracking_number']);

        // Shown and copyable, but never linked to the courier's site, which would not know it.
        $s = orderShipment(['courier' => 'jnt', 'tracking_number' => 'TP-00000123']);
        $this->assertSame('TP-00000123', $s['number']);
        $this->assertNull($s['url']);
    }

    public function testShipmentDisplay(): void
    {
        $this->assertNull(orderShipment([]));
        $this->assertNull(orderShipment(['courier' => 'nope']));

        $jnt = orderShipment(['courier' => 'jnt', 'tracking_number' => 'AB-123']);
        $this->assertSame('J&T Express', $jnt['courier']);
        $this->assertSame('AB-123', $jnt['number']);
        $this->assertSame('https://www.jtexpress.ph/trajectoryQuery?flag=1&waybillNo=AB-123', $jnt['url']);

        $link = orderShipment(['courier' => 'lalamove', 'tracking_number' => 'https://share.lalamove.com/?abc']);
        $this->assertNull($link['number']);
        $this->assertSame('https://share.lalamove.com/?abc', $link['url']);

        $grab = orderShipment(['courier' => 'grab', 'tracking_number' => 'GRAB1234']);
        $this->assertSame('GRAB1234', $grab['number']);
        $this->assertNull($grab['url']);   // no public tracking page to send a number to

        // A value that got into the column some other way never becomes a link.
        $this->assertNull(orderShipment(['courier' => 'lbc', 'tracking_number' => 'javascript:alert(1)'])['url']);

        $this->assertSame('Own Rider, rider Juan (09171234567)',
            orderShipmentText(['courier' => 'rider', 'rider_name' => 'Juan', 'rider_phone' => '09171234567']));
    }
}
