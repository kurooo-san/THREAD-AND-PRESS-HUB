<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/apparel-config.php';

final class CustomPriceTest extends TestCase
{
    protected function setUp(): void
    {
        customPrices(customPriceDefaults());   // never depend on a saved file
    }

    protected function tearDown(): void
    {
        customPrices(customPriceDefaults());
    }

    public function testDefaultsKeepTheOriginalTotals(): void
    {
        // (850 + 100 + 2 x 25) x 2 = 2000, less 20%
        $p = customDesignPrice('tshirt', 'medium', 2, 3, 'senior');
        $this->assertEquals(2000, $p['subtotal']);
        $this->assertEquals(1600, $p['total']);

        // 950 + 300 + 2 x 50 + 80 = 1430
        $this->assertEquals(1430, customDesignPrice('corp_polo', 'full', 1, 1, 'regular', 2, true)['total']);
    }

    public function testSavedPricesReachEveryReader(): void
    {
        $prices = customPriceDefaults();
        $prices['base']['tshirt']   = 900;
        $prices['print']['medium']  = 0;
        $prices['extra']['color']   = 10;
        $prices['extra']['sleeve']  = 60;
        customPrices($prices);

        $this->assertSame(900, getApparelConfig()['tshirt']['base']);
        $this->assertSame(1250, getApparelConfig()['hoodie']['base']);
        $this->assertSame(0, getPrintSizePrices()['medium']);
        $this->assertSame(60, getExtraPrintPrices()['sleeve']);
        // 900 + 0 + 2 x 10 + 60 = 980
        $this->assertEquals(980, customDesignPrice('tshirt', 'medium', 1, 3, 'regular', 1)['total']);
    }

    public function testValidation(): void
    {
        $this->assertNull(customPriceError('0', 'print'));
        $this->assertNull(customPriceError('50000', 'extra'));
        $this->assertNull(customPriceError('50', 'base'));
        foreach (['', '-1', '50001', '12.5', 'abc', '1e3', [], null] as $bad) {
            $this->assertNotNull(customPriceError($bad, 'print'));
        }
        $this->assertNotNull(customPriceError('49', 'base'));   // would fall under PayMongo's minimum
    }

    public function testBadSavedValuesFallBackPerField(): void
    {
        $merged = customPricesMerge([
            'base'  => ['tshirt' => 900, 'hoodie' => 1, 'unknown' => 500],
            'print' => 'junk',
            'extra' => ['color' => '30', 'logo' => -5],
        ]);
        $defaults = customPriceDefaults();

        $this->assertSame(900, $merged['base']['tshirt']);
        $this->assertSame($defaults['base']['hoodie'], $merged['base']['hoodie']);
        $this->assertArrayNotHasKey('unknown', $merged['base']);
        $this->assertSame($defaults['print'], $merged['print']);
        $this->assertSame(30, $merged['extra']['color']);
        $this->assertSame($defaults['extra']['logo'], $merged['extra']['logo']);
    }

    public function testLargestOrderFitsTheDatabaseColumns(): void
    {
        $prices = customPriceDefaults();
        array_walk_recursive($prices, static function (&$v): void { $v = CUSTOM_PRICE_MAX; });
        customPrices($prices);

        $p = customDesignPrice('tshirt', 'full', 100, 10, 'regular', 2, true);
        $this->assertLessThan(100000000, $p['total']);   // DECIMAL(10,2)
    }
}
