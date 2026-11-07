<?php

/**
 * Couple Wear for the Virtual Try-On: one photo of two people, each wearing
 * their own garment of the set.
 *
 * It takes two AI calls. Asked to do everything at once, the model often pasted
 * the picture of the flat shirt onto the clothes or left a grey top grey (in
 * test runs, one good result in four). Changing the tops first and printing on
 * them second gave four in four.
 */

/**
 * Splits a couple mockup (Partner A and B side by side, see
 * generateCoupleSideImage in custom-design.php) into one PNG per partner.
 * Each garment goes to the AI as its own image, so it cannot blend the two.
 *
 * @return array{0: string, 1: string}|null [Partner A, Partner B], or null if it is not such a mockup
 */
function coupleMockupHalves(string $bytes): ?array
{
    $src = function_exists('imagecreatefromstring') ? @imagecreatefromstring($bytes) : false;
    if (!$src) return null;
    $w = imagesx($src);
    $h = imagesy($src);
    if ($w <= $h) {   // two garments side by side are always wider than tall
        imagedestroy($src);
        return null;
    }
    $half = (int) floor($w / 2);
    $out = [];
    foreach ([0, $half] as $x) {
        $part = imagecrop($src, ['x' => $x, 'y' => 0, 'width' => $half, 'height' => $h]);
        if (!$part) {
            imagedestroy($src);
            return null;
        }
        ob_start();
        imagepng($part);
        $out[] = (string) ob_get_clean();
        imagedestroy($part);
    }
    imagedestroy($src);
    return $out;
}

/**
 * The fabric colour of one half of a couple mockup, as #RRGGBB. Read beside the
 * chest, at mid height: inside the garment on every couple shape, left of where
 * a centred print goes. The AI keeps the person's old colour if only shown it.
 */
function coupleGarmentColor(string $png): string
{
    $im = @imagecreatefromstring($png);
    if (!$im) return '';
    $rgb = imagecolorat($im, (int) (imagesx($im) * 0.27), (int) (imagesy($im) * 0.5));
    imagedestroy($im);
    return sprintf('#%06X', $rgb & 0xFFFFFF);
}

/** A colour for the prompt. The AI ignores a white hex code, and leaves a grey shirt grey, unless white is named. */
function coupleColorWords(string $hex): string
{
    $near = preg_match('/^#[Ff][0-9A-Fa-f][Ff][0-9A-Fa-f][Ff][0-9A-Fa-f]$/', $hex) === 1;
    return $near ? 'bright clean white (' . $hex . ')' : 'hex ' . $hex;
}

/** "Couple Hoodie Set" => "hoodie". */
function coupleGarmentWord(string $label): string
{
    return strtolower(trim(str_replace(['Couple', 'Set'], '', $label)));
}

/** First AI call: the photo only. Both people get a plain garment in its fabric colour. */
function coupleRecolorPrompt(string $label, string $leftColor, string $rightColor): string
{
    $garment = coupleGarmentWord($label);
    return 'Edit this photo, which should show two people. Change only the top each person wears into a plain, unprinted '
        . $garment . ': ' . coupleColorWords($leftColor) . ' on the person on the LEFT of the photo, and '
        . coupleColorWords($rightColor) . ' on the person on the RIGHT of the photo. '
        . 'Cover the whole top including the sleeves, collar and hem, with natural folds and shadows. '
        . 'No part of the old colour may remain, even when it is very different from the new one. '
        . 'Keep both faces, hair, skin tones, poses, the lighting, the background and the framing exactly as they are. '
        . 'If the photo does not clearly show two people, do not generate an image and do not invent a person: '
        . 'reply only with the text NOT_TWO_PEOPLE. Otherwise output only the final image.';
}

/** Second AI call: the recoloured photo + left garment + right garment, in that order. */
function couplePrintPrompt(): string
{
    return 'Edit the FIRST image, a real photo of two people wearing plain garments. The SECOND and THIRD images each show '
        . 'one garment laid flat, only to show the artwork to print. '
        . 'Print on the person on the LEFT only the text and graphics from the SECOND image, and on the person on the RIGHT '
        . 'only the text and graphics from the THIRD image, in the same position and at the same relative size, directly on '
        . 'the fabric, curving with the body and folds. '
        . 'Copy all artwork exactly: the same font, weight, letter shapes, spelling and colours. '
        . 'NEVER paste the picture of the flat garment; no outline or silhouette of a garment may appear. '
        . 'Do not change the garment colours. Do not swap, add, remove or mirror any artwork. '
        . 'Keep everything else exactly as it is. Output only the final image.';
}

/**
 * The AI's output ratio closest to the photo's. Left alone, the model takes the
 * shape of the last image (a garment) and invents what the photo does not show.
 */
function tryOnAspectRatio(int $w, int $h): string
{
    $best = '1:1';
    $bestDiff = INF;
    foreach (['1:1', '2:3', '3:2', '3:4', '4:3', '4:5', '5:4', '9:16', '16:9'] as $ratio) {
        [$a, $b] = explode(':', $ratio);
        $diff = abs(log(($w / $h) / ($a / $b)));
        if ($diff < $bestDiff) {
            $best = $ratio;
            $bestDiff = $diff;
        }
    }
    return $best;
}
