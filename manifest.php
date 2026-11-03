<?php
declare(strict_types=1);

/*
 * Web app manifest for the installable app (was manifest.json). Built in PHP
 * so the app name and icons follow admin Branding (includes/brand.php).
 * Paths are relative to this file, which sits in the web root.
 */
require_once __DIR__ . '/includes/brand.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');

$icon192 = brandIconUrl('192');
echo json_encode([
    'name'             => brand('name'),
    'short_name'       => brand('short_name'),
    'description'      => 'Shop apparel, design your own, and try it on with AI.',
    'id'               => './',
    'start_url'        => './index.php?source=pwa',
    'scope'            => './',
    'display'          => 'standalone',
    'orientation'      => 'any',
    'background_color' => '#131313',
    'theme_color'      => '#131313',
    'categories'       => ['shopping', 'lifestyle'],
    'icons'            => [
        ['src' => $icon192, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => brandIconUrl('512'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => brandIconUrl('maskable'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts'        => [
        ['name' => 'Shop', 'url' => './shop.php', 'icons' => [['src' => $icon192, 'sizes' => '192x192']]],
        ['name' => 'Design Studio', 'url' => './custom-design.php', 'icons' => [['src' => $icon192, 'sizes' => '192x192']]],
        ['name' => 'Cart', 'url' => './cart.php', 'icons' => [['src' => $icon192, 'sizes' => '192x192']]],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
