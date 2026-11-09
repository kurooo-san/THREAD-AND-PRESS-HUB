<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/brand.php';

final class BrandTest extends TestCase
{
    protected function tearDown(): void
    {
        brandSettings(BRAND_DEFAULTS);
    }

    public function testDefaultsUseShippedLogoAndIcons(): void
    {
        brandSettings(BRAND_DEFAULTS);
        $this->assertSame('Thread & Press Hub', brand('name'));
        $this->assertSame(BRAND_DEFAULT_LOGO, brandLogoUrl(false));
        $this->assertSame(BRAND_DEFAULT_LOGO_DARK, brandLogoUrl(true));
        $this->assertSame(BRAND_DEFAULT_ICONS['180'], brandIconUrl('180'));
        $this->assertStringNotContainsString('brand-custom', brandLogoImgs('brand-logo'));
    }

    public function testOldStoreNameInFaqTextBecomesTheCurrentName(): void
    {
        brandSettings(['name' => 'John $1 Chloe'] + BRAND_DEFAULTS);
        $this->assertSame('At John $1 Chloe!', brandCurrentName('At Thread and Press Hub!'));
        $this->assertSame('John $1 Chloe team', brandCurrentName('Thread & Press team'));
        $this->assertSame('We thread and press shirts', brandCurrentName('We thread and press shirts'));
    }

    public function testMissingOrTraversalFilesFallBackToShippedLogo(): void
    {
        brandSettings(['logo' => '../../includes/config.php', 'logo_dark' => 'logo-missing.png', 'icon' => '../evil'] + BRAND_DEFAULTS);
        $this->assertSame(BRAND_DEFAULT_LOGO, brandLogoUrl(false));
        $this->assertSame(BRAND_DEFAULT_LOGO_DARK, brandLogoUrl(true));
        $this->assertSame(BRAND_DEFAULT_ICONS['32'], brandIconUrl('32'));
    }

    public function testInvoiceTaglineIsEditableAndCanBeBlank(): void
    {
        brandSettings(BRAND_DEFAULTS);
        $this->assertSame('Premium Apparel &amp; Custom Designs', brandHtml('invoice_tagline'));

        brandSettings(['invoice_tagline' => '<b>Prints</b>'] + BRAND_DEFAULTS);
        $this->assertSame('&lt;b&gt;Prints&lt;/b&gt;', brandHtml('invoice_tagline'));

        brandSettings(['invoice_tagline' => ''] + BRAND_DEFAULTS);
        $this->assertSame('', brand('invoice_tagline'));
    }

    public function testNameIsEscapedForHtml(): void
    {
        brandSettings(['name' => 'A & <b>"Co"</b>'] + BRAND_DEFAULTS);
        $this->assertSame('A &amp; &lt;b&gt;&quot;Co&quot;&lt;/b&gt;', brandHtml('name'));
        $this->assertStringNotContainsString('<b>', brandLogoImgs('x', '', brand('name')));
    }
}
