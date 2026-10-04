<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/size-estimate.php';

final class SizeEstimateTest extends TestCase
{
    private const ALL = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

    public function testTopsFollowTheChestGuide(): void
    {
        $this->assertSame('M', estimateSize('t-shirts', 170, 68, 'regular', self::ALL)['size']);   // ~39 in
        $this->assertSame('L', estimateSize('t-shirts', 170, 68, 'loose', self::ALL)['size']);     // +2 in room
        $this->assertSame('XS', estimateSize('t-shirts', 150, 42, 'regular', self::ALL)['size']);
        $this->assertSame('XXL', estimateSize('hoodies', 185, 95, 'regular', self::ALL)['size']);
    }

    public function testPantsUseWaistAndDressesUseBust(): void
    {
        $this->assertSame('M', estimateSize('pants', 170, 68, 'regular', ['S', 'M', 'L', 'XL', 'XXL'])['size']);
        $this->assertSame('S', estimateSize('pants', 160, 52, 'regular', ['S', 'M', 'L', 'XL', 'XXL'])['size']);
        $this->assertSame('S', estimateSize('dresses', 160, 55, 'regular', ['XS', 'S', 'M', 'L', 'XL'])['size']);
    }

    public function testOnlyPicksSizesTheProductHas(): void
    {
        $this->assertSame('M', estimateSize('t-shirts', 150, 42, 'regular', ['M', 'L'])['size']);       // nearest bigger
        $this->assertSame('L', estimateSize('t-shirts', 200, 140, 'regular', ['S', 'M', 'L'])['size']); // largest it has
    }

    public function testReasonNamesTheSize(): void
    {
        $r = estimateSize('pants', 170, 68, 'regular', ['S', 'M', 'L']);
        $this->assertStringContainsString('size M', $r['reason']);
        $this->assertStringContainsString('waist', $r['reason']);
    }
}
