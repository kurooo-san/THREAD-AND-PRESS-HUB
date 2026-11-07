<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/tryon-couple.php';

final class TryOnCoupleTest extends TestCase
{
    private function png(int $w, int $h, ?array $rightColor = null): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 0, 0));
        if ($rightColor) {
            imagefilledrectangle($im, (int) ($w / 2), 0, $w - 1, $h - 1, imagecolorallocate($im, ...$rightColor));
        }
        ob_start();
        imagepng($im);
        return (string) ob_get_clean();
    }

    public function testSplitsTheMockupIntoPartnerAThenPartnerB(): void
    {
        [$a, $b] = coupleMockupHalves($this->png(800, 500, [0, 0, 255]));

        $this->assertSame([400, 500], array_slice(getimagesizefromstring($a), 0, 2));
        $this->assertSame([400, 500], array_slice(getimagesizefromstring($b), 0, 2));

        $color = fn(string $png) => imagecolorat(imagecreatefromstring($png), 200, 250);
        $this->assertSame(0xFF0000, $color($a));   // left half: Partner A
        $this->assertSame(0x0000FF, $color($b));   // right half: Partner B
    }

    public function testReadsTheFabricColourOfEachHalf(): void
    {
        [$a, $b] = coupleMockupHalves($this->png(800, 500, [0, 31, 63]));
        $this->assertSame('#FF0000', coupleGarmentColor($a));
        $this->assertSame('#001F3F', coupleGarmentColor($b));
        $this->assertSame('', coupleGarmentColor('not an image'));
    }

    public function testNamesWhiteInThePrompt(): void
    {
        $this->assertStringContainsString('white', coupleColorWords('#FFFFFF'));
        $this->assertStringContainsString('white', coupleColorWords('#F8F9FA'));
        $this->assertSame('hex #001F3F', coupleColorWords('#001F3F'));
        $this->assertSame('hex #FF0000', coupleColorWords('#FF0000'));
    }

    public function testRejectsWhatIsNotACoupleMockup(): void
    {
        $this->assertNull(coupleMockupHalves('not an image'));
        $this->assertNull(coupleMockupHalves($this->png(400, 500)));   // a single garment
    }

    public function testAspectRatioFollowsThePhoto(): void
    {
        $this->assertSame('1:1', tryOnAspectRatio(1024, 1024));
        $this->assertSame('4:3', tryOnAspectRatio(1280, 960));
        $this->assertSame('3:4', tryOnAspectRatio(960, 1280));
        $this->assertSame('16:9', tryOnAspectRatio(1280, 720));
        $this->assertSame('9:16', tryOnAspectRatio(720, 1280));
    }

    public function testRecolorPromptNamesTheGarmentAndRefusesASinglePerson(): void
    {
        $this->assertSame('hoodie', coupleGarmentWord('Couple Hoodie Set'));
        $this->assertSame('t-shirt', coupleGarmentWord('Couple T-Shirt Set'));

        $prompt = coupleRecolorPrompt('Couple Hoodie Set', '#FFFFFF', '#001F3F');
        $this->assertStringContainsString('unprinted hoodie', $prompt);
        $this->assertStringContainsString('white (#FFFFFF) on the person on the LEFT', $prompt);
        $this->assertStringContainsString('hex #001F3F on the person on the RIGHT', $prompt);
        $this->assertStringContainsString('NOT_TWO_PEOPLE', $prompt);
    }

    public function testPrintPromptKeepsEachSideAndForbidsPastingTheGarment(): void
    {
        $prompt = couplePrintPrompt();
        $this->assertStringContainsString('LEFT only the text and graphics from the SECOND image', $prompt);
        $this->assertStringContainsString('RIGHT only the text and graphics from the THIRD image', $prompt);
        $this->assertStringContainsString('NEVER paste', $prompt);
    }
}
