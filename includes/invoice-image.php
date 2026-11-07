<?php

/**
 * An <img> for a PDF invoice, embedded in the HTML because Dompdf runs without
 * remote access. The path comes from the database, so only a real image inside
 * uploads/ or images/ gets in. Anything else returns '': the invoice still
 * prints, just without the picture.
 */
function invoiceImage(string $path, int $maxW, int $maxH): string
{
    $root = dirname(__DIR__);
    $file = realpath($root . '/' . $path);
    if ($file === false || !is_file($file)) return '';

    $allowed = false;
    foreach (['uploads', 'images'] as $dir) {
        $base = realpath($root . '/' . $dir);   // realpath: uploads/ can be a mounted volume
        if ($base !== false && strpos($file, $base . DIRECTORY_SEPARATOR) === 0) $allowed = true;
    }
    if (!$allowed) return '';

    $size = @getimagesize($file);
    if ($size === false || !in_array($size['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) return '';

    $scale = min($maxW / $size[0], $maxH / $size[1], 1);
    return '<img alt="" src="data:' . $size['mime'] . ';base64,' . base64_encode((string) file_get_contents($file)) . '"'
        . ' style="width:' . round($size[0] * $scale) . 'px;height:' . round($size[1] * $scale) . 'px;">';
}
