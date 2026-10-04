<?php

declare(strict_types=1);

/**
 * Formula size pick, used by includes/tryon-size.php when the AI is
 * unavailable (busy, no key, bad answer) so the customer still gets a size.
 *
 * ponytail: rough height/weight → chest/waist estimate, not a body scan;
 * good enough to land within one size of the guide.
 */

// Upper bound (inches) of each size in the store size guide.
const SIZE_UPPER_IN = [
    'tops'  => ['XS' => 34, 'S' => 36, 'M' => 40, 'L' => 44, 'XL' => 48, 'XXL' => 52], // chest
    'pants' => ['S' => 30, 'M' => 32, 'L' => 34, 'XL' => 36, 'XXL' => 38],            // waist
    'dress' => ['XS' => 32, 'S' => 34, 'M' => 36, 'L' => 40, 'XL' => 42],             // bust
];

/**
 * @param string[] $available the product's sizes, in the order the shop lists them
 * @return array{size: string, reason: string}
 */
function estimateSize(string $category, float $heightCm, float $weightKg, string $fit, array $available): array
{
    $kind  = $category === 'pants' ? 'pants' : ($category === 'dresses' ? 'dress' : 'tops');
    $label = $kind === 'pants' ? 'waist' : ($kind === 'dress' ? 'bust' : 'chest');

    $inches = $kind === 'pants'
        ? 0.3 * $weightKg + 0.05 * $heightCm + 3
        : 0.3 * $weightKg + 0.1 * $heightCm + ($kind === 'dress' ? 0 : 2);
    // Room wanted on top of the body: snug, true to size, or roomy.
    $target = $inches + (['fitted' => -1, 'regular' => 0, 'loose' => 2][$fit] ?? 0);

    $table = SIZE_UPPER_IN[$kind];
    $order = array_keys($table);
    $want  = end($order);
    foreach ($table as $size => $upper) {
        if ($target <= $upper) { $want = $size; break; }
    }

    // Closest size this product actually has (by position in the guide).
    $best = $available[0];
    $bestGap = PHP_INT_MAX;
    foreach ($available as $s) {
        $pos = array_search(strtoupper($s), $order, true);
        if ($pos === false) continue;
        $gap = abs($pos - array_search($want, $order, true));
        if ($gap < $bestGap) { $best = $s; $bestGap = $gap; }
    }

    return [
        'size'   => $best,
        'reason' => 'Based on your height and weight (about ' . round($inches) . ' in ' . $label
            . '), size ' . $best . ' should give you the ' . $fit . ' fit you want.',
    ];
}
