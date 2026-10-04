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
 *   model3d   : 3D model for the live preview (js/design-3d.js), or null to keep
 *               the flat preview. rotY turns the model to face front (degrees),
 *               chestY is the print centre as a fraction of the model's height
 *               from the bottom, printW the print width as a fraction of its width.
 *               sleeve {x, y, w} places the sleeve prints the same way (x out
 *               from the middle; optional z, default 0.6, turns them toward
 *               the front), or null for a sleeveless model. Optional
 *               frameW (default 1): the fraction of the model's width the
 *               camera must fit, for models with spread arms.
 *               Models are Draco-compressed copies of images/models/ in
 *               images/models/web/.
 */

function getApparelConfig()
{
    $m = [
        'tshirt' => ['src' => 'images/models/web/tshirt.glb', 'rotY' => 30, 'chestY' => 0.62, 'printW' => 0.30,
                     'sleeve' => ['x' => 0.35, 'y' => 0.745, 'w' => 0.11]],
        'hoodie' => ['src' => 'images/models/web/hoodie.glb', 'rotY' => 0,  'chestY' => 0.62, 'printW' => 0.30,
                     'sleeve' => ['x' => 0.40, 'y' => 0.70, 'w' => 0.12]],
        'polo'   => ['src' => 'images/models/web/polo.glb',   'rotY' => 0,  'chestY' => 0.60, 'printW' => 0.30,
                     'sleeve' => ['x' => 0.42, 'y' => 0.66, 'w' => 0.12]],
        // Colourless copy of images/models/corporate-model/polo-long-sleeve.glb.
        // Its arms are spread (A-pose), so the torso is only a third of the
        // width: the print fractions are small, and frameW frames the camera
        // on 78% of the width so the shirt is not tiny; sleeve z turns the
        // sleeve prints toward the front. printW/chestY are set so the logo
        // zone lands level with, and the same size as, the model's pocket.
        'longsleeve' => ['src' => 'images/models/web/longsleeve.glb', 'rotY' => 0, 'chestY' => 0.546, 'printW' => 0.238,
                         'frameW' => 0.78,
                         'sleeve' => ['x' => 0.25, 'y' => 0.72, 'w' => 0.06, 'z' => 1.5]],
        // The model's V-neck runs to mid-body, so the print sits below it. No sleeves.
        // printW/chestY are for the wider corporate design area ($corpArea).
        'vest'   => ['src' => 'images/models/web/vest.glb',   'rotY' => 0,  'chestY' => 0.381, 'printW' => 0.403,
                     'sleeve' => null],
    ];
    // Corporate polo: the polo model, with the print scaled for $corpArea so a
    // mockup pixel is the same size on the shirt as on the basic polo.
    $m['corp_polo'] = array_merge($m['polo'], ['chestY' => 0.609, 'printW' => 0.386]);

    // Corporate wear shares one design area and logo zone. The logo zone is the
    // mirror of the chest pocket (x 240-290, y 120-178 on the corporate polo
    // mockup): the same width, level with the pocket's centre, on the other
    // side. It is square because the Logo Designer's canvas is. The area is
    // widened to x 110-290 (centred on the shirt) so the zone lies inside it.
    $corpArea = ['x' => 110, 'y' => 120, 'w' => 180, 'h' => 180];
    $corpLogo = ['x' => 110, 'y' => 124, 'w' => 50, 'h' => 50];

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
            'model3d'  => $m['tshirt'],
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
            'model3d'  => $m['hoodie'],
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
            'model3d'  => $m['polo'],
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
            'model3d'  => $m['tshirt'],
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
            'model3d'  => $m['hoodie'],
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
            'model3d'  => $m['polo'],
        ],

        // ---- Corporate Wear (logo placement guide) ----
        'corp_polo' => [
            'label'    => 'Corporate Polo',
            'icon'     => 'fa-user-tie',
            'group'    => 'corporate',
            'base'     => 950,
            'shape'    => 'corp_polo',
            'area'     => $corpArea,
            'couple'   => false,
            'logoZone' => $corpLogo,
            'model3d'  => $m['corp_polo'],
        ],
        'corp_longsleeve' => [
            'label'    => 'Corporate Long Sleeve',
            'icon'     => 'fa-user-tie',
            'group'    => 'corporate',
            'base'     => 1050,
            'shape'    => 'longsleeve',
            'area'     => $corpArea,
            'couple'   => false,
            'logoZone' => $corpLogo,
            'model3d'  => $m['longsleeve'],
        ],
        'corp_vest' => [
            'label'    => 'Corporate Vest',
            'icon'     => 'fa-user-tie',
            'group'    => 'corporate',
            'base'     => 1100,
            'shape'    => 'vest',
            'area'     => $corpArea,
            'couple'   => false,
            'logoZone' => $corpLogo,
            'model3d'  => $m['vest'],
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

/**
 * Extra print positions, in PHP pesos: each printed sleeve, and the corporate
 * logo. A sleeve is charged once per set even when both Couple Wear partners
 * have one (the set is priced as one item, like its base price).
 */
function getExtraPrintPrices()
{
    return ['sleeve' => 50, 'logo' => 80];
}

/**
 * The extra prints of a saved design, from the paths the server stored in its
 * design_data (never from the browser): ['sleeves' => 0..2, 'logo' => bool].
 */
function designExtraPrints(array $designData)
{
    $x = isset($designData['extraPrints']) && is_array($designData['extraPrints']) ? $designData['extraPrints'] : [];
    return [
        'sleeves' => (int) !empty($x['left']) + (int) !empty($x['right']),
        'logo'    => !empty($x['logo']),
    ];
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
 *               discount:float, total:float, colorsUsed:int,
 *               extras:float, sleeves:int, logo:bool}
 */
function customDesignPrice(string $apparelType, string $printSize, int $quantity, int $colorsUsed, string $discountType, int $sleeves = 0, bool $logo = false)
{
    $cfg    = getApparelConfig();
    $prints = getPrintSizePrices();
    $extra  = getExtraPrintPrices();

    $base  = isset($cfg[$apparelType]) ? (float) $cfg[$apparelType]['base'] : 0.0;
    $print = $prints[$printSize] ?? $prints['medium'];

    // The first colour is included; each extra one costs P25.
    $colorsUsed = max(1, $colorsUsed);
    $color      = ($colorsUsed - 1) * 25;

    // Sleeve and logo prints.
    $sleeves = max(0, min(2, $sleeves));
    $extras  = $sleeves * $extra['sleeve'] + ($logo ? $extra['logo'] : 0);

    $quantity = max(1, min(100, $quantity));
    $unit     = $base + $print + $color + $extras;
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
        'extras'       => (float) $extras,
        'sleeves'      => $sleeves,
        'logo'         => $logo,
    ];
}
