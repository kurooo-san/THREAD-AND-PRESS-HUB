<?php
/**
 * Apparel Type Configuration — single source of truth for the Custom Design Tool.
 *
 * This file is consumed in two places:
 *   1. custom-design.php  — injected into the page as JSON and used to build the
 *      apparel-type tabs, mockup selection, design areas, logo guides and pricing.
 *   2. includes/custom-design-ajax.php — used to validate the submitted product_type.
 *
 * ADDING A NEW APPAREL TYPE (no JS changes required):
 *   Add a new entry below. The only field that must reference existing code is
 *   "shape" — it picks which procedural SVG mockup to draw and must be one of the
 *   shapes defined in custom-design.php (tshirt, hoodie, polo, corp_polo,
 *   longsleeve, vest). Everything else (label, price, group, design area, couple
 *   mode, logo zone) is pure data and can be changed freely.
 *
 * Coordinate system for "area" and "logoZone" is the 400x500 mockup canvas space.
 *
 *   group     : 'basic' | 'couple' | 'corporate'  (which tab the type appears under)
 *   couple    : true  => two side-by-side garments (Partner A / Partner B)
 *   logoZone  : rect  => draws a dashed logo-placement guide on the canvas, or null
 *   base      : base price in PHP pesos (couple values are per set)
 */

function getApparelConfig()
{
    return [
        // ---- Basic ----
        'tshirt' => [
            'label'    => 'T-Shirt',
            'icon'     => 'fa-shirt',
            'group'    => 'basic',
            'base'     => 850,
            'shape'    => 'tshirt',
            'area'     => ['x' => 130, 'y' => 120, 'w' => 140, 'h' => 180],
            'couple'   => false,
            'logoZone' => null,
        ],
        'hoodie' => [
            'label'    => 'Hoodie',
            'icon'     => 'fa-vest',
            'group'    => 'basic',
            'base'     => 1250,
            'shape'    => 'hoodie',
            'area'     => ['x' => 130, 'y' => 130, 'w' => 140, 'h' => 160],
            'couple'   => false,
            'logoZone' => null,
        ],
        'polo' => [
            'label'    => 'Polo',
            'icon'     => 'fa-shirt',
            'group'    => 'basic',
            'base'     => 1150,
            'shape'    => 'polo',
            'area'     => ['x' => 130, 'y' => 120, 'w' => 140, 'h' => 180],
            'couple'   => false,
            'logoZone' => null,
        ],

        // ---- Couple Wear (per-set price) ----
        'couple_tshirt' => [
            'label'    => 'Couple T-Shirt Set',
            'icon'     => 'fa-shirt',
            'group'    => 'couple',
            'base'     => 1500,
            'shape'    => 'tshirt',
            'area'     => ['x' => 130, 'y' => 120, 'w' => 140, 'h' => 180],
            'couple'   => true,
            'logoZone' => null,
        ],
        'couple_hoodie' => [
            'label'    => 'Couple Hoodie Set',
            'icon'     => 'fa-vest',
            'group'    => 'couple',
            'base'     => 1500,
            'shape'    => 'hoodie',
            'area'     => ['x' => 130, 'y' => 130, 'w' => 140, 'h' => 160],
            'couple'   => true,
            'logoZone' => null,
        ],
        'couple_polo' => [
            'label'    => 'Couple Polo Set',
            'icon'     => 'fa-shirt',
            'group'    => 'couple',
            'base'     => 1500,
            'shape'    => 'polo',
            'area'     => ['x' => 130, 'y' => 120, 'w' => 140, 'h' => 180],
            'couple'   => true,
            'logoZone' => null,
        ],

        // ---- Corporate Wear (logo placement guide) ----
        'corp_polo' => [
            'label'    => 'Corporate Polo',
            'icon'     => 'fa-user-tie',
            'group'    => 'corporate',
            'base'     => 950,
            'shape'    => 'corp_polo',
            'area'     => ['x' => 130, 'y' => 130, 'w' => 140, 'h' => 170],
            'couple'   => false,
            'logoZone' => ['x' => 150, 'y' => 150, 'w' => 72, 'h' => 72],
        ],
        'corp_longsleeve' => [
            'label'    => 'Corporate Long Sleeve',
            'icon'     => 'fa-user-tie',
            'group'    => 'corporate',
            'base'     => 1050,
            'shape'    => 'longsleeve',
            'area'     => ['x' => 130, 'y' => 120, 'w' => 140, 'h' => 180],
            'couple'   => false,
            'logoZone' => ['x' => 150, 'y' => 150, 'w' => 72, 'h' => 72],
        ],
        'corp_vest' => [
            'label'    => 'Corporate Vest',
            'icon'     => 'fa-user-tie',
            'group'    => 'corporate',
            'base'     => 1100,
            'shape'    => 'vest',
            'area'     => ['x' => 142, 'y' => 135, 'w' => 116, 'h' => 165],
            'couple'   => false,
            'logoZone' => ['x' => 156, 'y' => 152, 'w' => 64, 'h' => 64],
        ],
    ];
}

/** Returns the list of valid apparel type keys (used for server-side validation). */
function getApparelTypeKeys()
{
    return array_keys(getApparelConfig());
}

/**
 * Print-size surcharges, in PHP pesos.
 *
 * Kept beside the base prices so a price change means editing one file.
 */
function getPrintSizePrices()
{
    return ['small' => 50, 'medium' => 100, 'large' => 180, 'full' => 300];
}

/** Human labels for the print sizes. */
function getPrintSizeLabels()
{
    return [
        'small'  => 'Small (4x4")',
        'medium' => 'Medium (8x8")',
        'large'  => 'Large (12x12")',
        'full'   => 'Full Print',
    ];
}

/**
 * Price one custom-design order.
 *
 * The ONE place custom-design pricing is worked out, so the estimate on a
 * "My Designs" card and the figure on the order page cannot drift apart.
 * Base prices come from getApparelConfig(), which also means an apparel type
 * that is not a t-shirt/hoodie/polo is priced correctly instead of falling
 * back to a placeholder.
 *
 * Every input is clamped here, so callers may pass raw request values.
 *
 * @return array{base:float, print:float, color:float, unit:float,
 *               quantity:int, subtotal:float, discountRate:float,
 *               discount:float, total:float, colorsUsed:int}
 */
function customDesignPrice(string $apparelType, string $printSize, int $quantity, int $colorsUsed, string $discountType)
{
    $cfg    = getApparelConfig();
    $prints = getPrintSizePrices();

    $base  = isset($cfg[$apparelType]) ? (float) $cfg[$apparelType]['base'] : 0.0;
    $print = $prints[$printSize] ?? $prints['medium'];

    // The first colour is included; each extra one costs P25.
    $colorsUsed = max(1, $colorsUsed);
    $color      = ($colorsUsed - 1) * 25;

    $quantity = max(1, min(100, $quantity));
    $unit     = $base + $print + $color;
    $subtotal = $unit * $quantity;

    $rate     = in_array($discountType, ['pwd', 'senior'], true) ? 0.20 : 0.0;
    $discount = round($subtotal * $rate, 2);

    return [
        'base'         => $base,
        'print'        => (float) $print,
        'color'        => (float) $color,
        'unit'         => $unit,
        'quantity'     => $quantity,
        'subtotal'     => $subtotal,
        'discountRate' => $rate,
        'discount'     => $discount,
        'total'        => $subtotal - $discount,
        'colorsUsed'   => $colorsUsed,
    ];
}
