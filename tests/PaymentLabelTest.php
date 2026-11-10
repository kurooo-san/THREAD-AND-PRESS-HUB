<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/payment-config.php';

final class PaymentLabelTest extends TestCase
{
    public function testCashLabelFollowsHowTheOrderIsCollected(): void
    {
        $this->assertSame('Cash on Delivery', paymentMethodLabel('cod'));
        $this->assertSame('Cash on Pickup', paymentMethodLabel('cod', true));
        $this->assertSame('Paid online (PayMongo)', paymentMethodLabel('paymongo', true));
        $this->assertSame('Not specified', paymentMethodLabel(null, true));
    }
}
