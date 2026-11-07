<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/invoice-image.php';

final class InvoiceImageTest extends TestCase
{
    public function testEmbedsARealImageScaledToFit(): void
    {
        // logo_sm.png is 160x159.
        $img = invoiceImage('images/logo/logo_sm.png', 80, 80);
        $this->assertStringContainsString('src="data:image/png;base64,', $img);
        $this->assertStringContainsString('width:80px;height:80px', $img);
    }

    public function testNeverEnlargesASmallImage(): void
    {
        $this->assertStringContainsString('width:160px;height:159px', invoiceImage('images/logo/logo_sm.png', 500, 500));
    }

    public function testRejectsAnythingElse(): void
    {
        $this->assertSame('', invoiceImage('', 80, 80));
        $this->assertSame('', invoiceImage('images/logo/missing.png', 80, 80));
        $this->assertSame('', invoiceImage('includes/config.php', 80, 80));                 // outside uploads/ and images/
        $this->assertSame('', invoiceImage('images/../includes/config.php', 80, 80));       // traversal
        $this->assertSame('', invoiceImage('data:image/png;base64,AAAA', 80, 80));          // not a stored file

        // A non-image inside an allowed folder.
        $fake = 'images/invoice-image-test.png';
        file_put_contents(__DIR__ . '/../' . $fake, '<?php echo 1;');
        try {
            $this->assertSame('', invoiceImage($fake, 80, 80));
        } finally {
            unlink(__DIR__ . '/../' . $fake);
        }
    }
}
