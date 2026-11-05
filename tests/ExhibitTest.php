<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/exhibit.php';

final class ExhibitTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];   // no real session under PHPUnit
    }

    protected function tearDown(): void
    {
        exhibitSettings(['user_id' => 0]);
        $_SESSION = [];
    }

    public function testLimitValidation(): void
    {
        foreach (['15', '100', '500', 100] as $ok) {
            $this->assertNull(exhibitLimitError($ok));
        }
        foreach (['14', '501', '', 'abc', '20.5', null, []] as $bad) {
            $this->assertNotNull(exhibitLimitError($bad));
        }
    }

    public function testBadSavedDataMeansOff(): void
    {
        foreach ([null, 'junk', [], ['user_id' => 0, 'limit' => 100], ['user_id' => -1, 'limit' => 100],
                  ['user_id' => 5], ['user_id' => 5, 'limit' => 99999]] as $bad) {
            $this->assertSame(0, exhibitNormalize($bad)['user_id']);
        }
        $this->assertSame(['user_id' => 5, 'limit' => 200], exhibitNormalize(['user_id' => '5', 'limit' => '200']));
    }

    public function testOnlyTheDemoAccountIsTheExhibitUser(): void
    {
        exhibitSettings(['user_id' => 7, 'limit' => 100]);
        $_SESSION['user_id'] = 7;
        $this->assertTrue(isExhibitUser());
        $_SESSION['user_id'] = 8;
        $this->assertFalse(isExhibitUser());
        unset($_SESSION['user_id']);
        $this->assertFalse(isExhibitUser());   // logged out
    }

    private function segment(int $marker, string $data): string
    {
        return "\xFF" . chr($marker) . pack('n', strlen($data) + 2) . $data;
    }

    public function testJpegLosesLocationAndKeepsTheImage(): void
    {
        $image = $this->segment(0xDB, 'quant') . $this->segment(0xC0, 'frame') . "\xFF\xDA\x00\x02scan-data\xFF\xE1still-image-data\xFF\xD9";
        $jpeg  = "\xFF\xD8"
            . $this->segment(0xE0, 'JFIF')
            . $this->segment(0xE1, "Exif\0\0GPS-14.5995")
            . $this->segment(0xE1, 'http://ns.adobe.com/xap/ XMP-secret')
            . $this->segment(0xED, 'Photoshop IPTC-secret')
            . $this->segment(0xFE, 'comment-secret')
            . $image;
        $clean = exhibitStripJpegMeta($jpeg);

        foreach (['GPS', 'XMP-secret', 'IPTC-secret', 'comment-secret'] as $gone) {
            $this->assertStringNotContainsString($gone, $clean);
        }
        // Everything else is byte for byte, including an E1 inside the image data.
        $this->assertSame("\xFF\xD8" . $this->segment(0xE0, 'JFIF') . $image, $clean);
    }

    public function testDamagedJpegIsRefused(): void
    {
        $this->assertNull(exhibitStripJpegMeta('not a jpeg'));
        $this->assertNull(exhibitStripJpegMeta(''));
        $this->assertNull(exhibitStripJpegMeta("\xFF\xD8"));                                   // no image data
        $this->assertNull(exhibitStripJpegMeta("\xFF\xD8\xFF\xE1\xFF\xFFshort"));              // length past the end
        $this->assertNull(exhibitStripJpegMeta("\xFF\xD8\xFF\xE1\x00\x01xxxx\xFF\xDA"));       // impossible length
        $this->assertNull(exhibitStripJpegMeta("\xFF\xD8garbage-not-a-marker"));
    }

    public function testBadSampleIdIsRefused(): void
    {
        foreach (['', '../../includes/config', 'abc', '*', 'ABCDEF0123456789'] as $bad) {
            $this->assertFalse(exhibitSampleDelete($bad));
        }
    }

    public function testOffMeansNobodyEvenWithNoLogin(): void
    {
        exhibitSettings(['user_id' => 0]);
        $_SESSION['user_id'] = 0;
        $this->assertFalse(isExhibitUser());
    }
}
