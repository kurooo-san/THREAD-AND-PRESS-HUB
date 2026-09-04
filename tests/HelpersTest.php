<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testSanitizeStripsTagsAndEscapesQuotes(): void
    {
        $this->assertSame('alert(&quot;x&quot;)', sanitizeInputPure('<script>alert("x")</script>'));
        $this->assertSame('hello', sanitizeInputPure('  hello  '));
    }

    public function testCalculateDiscountKnownTypes(): void
    {
        $this->assertSame(0.0, calculateDiscountPure('regular'));
        $this->assertSame(0.20, calculateDiscountPure('pwd'));
        $this->assertSame(0.20, calculateDiscountPure('senior'));
        $this->assertSame(0.0, calculateDiscountPure('unknown'));
    }

    public function testApplyDiscountMath(): void
    {
        $r = applyDiscountPure(1000.0, 0.20);
        $this->assertSame(200.0, $r['discount_amount']);
        $this->assertSame(800.0, $r['total']);

        $r = applyDiscountPure(1234.56, 0.0);
        $this->assertSame(0.0, $r['discount_amount']);
        $this->assertSame(1234.56, $r['total']);
    }
}
