<?php

/**
 * Delivery zones and their fees.
 *
 * The store ships from Cainta, Rizal, so Rizal is the base rate and every
 * other zone is priced by distance from it.
 *
 * TO CHANGE A PRICE, EDIT ONLY THE 'fee' VALUES BELOW. Nothing else in the
 * codebase hardcodes a delivery amount — checkout.php reads the fee from
 * here on the server, and hands the same table to the browser so the order
 * summary and the recorded total can never disagree.
 *
 * Requires includes/config.php first (for sanitizeInput()).
 */

if (!defined('DELIVERY_ZONES')) {

    define('DELIVERY_ZONES', [
        'rizal'     => ['label' => 'Rizal (Cainta, Taytay, Antipolo, nearby)', 'fee' => 50.00],
        'ncr'       => ['label' => 'Metro Manila / NCR',                        'fee' => 100.00],
        'nearby'    => ['label' => 'Bulacan, Cavite, Laguna, Batangas, Quezon, Pampanga', 'fee' => 150.00],
        'luzon'     => ['label' => 'Rest of Luzon',                             'fee' => 200.00],
        'visayas'   => ['label' => 'Visayas',                                   'fee' => 250.00],
        'mindanao'  => ['label' => 'Mindanao',                                  'fee' => 300.00],
    ]);

    /** Zone used when the customer has not chosen one yet. */
    define('DELIVERY_ZONE_DEFAULT', 'rizal');

    /**
     * Authoritative fee for a zone. Store pickup is always free, and an
     * unrecognised zone falls back to the default rather than to zero —
     * a bad value must never make delivery free.
     */
    function deliveryFee(string $zone, string $method = 'delivery'): float
    {
        if ($method === 'pickup') {
            return 0.00;
        }
        $zones = DELIVERY_ZONES;
        if (!isset($zones[$zone])) {
            $zone = DELIVERY_ZONE_DEFAULT;
        }
        return (float) $zones[$zone]['fee'];
    }

    /** True when the key names a real zone. */
    function isDeliveryZone(string $zone): bool
    {
        return array_key_exists($zone, DELIVERY_ZONES);
    }

    /**
     * Zone named by a piece of free-text address, or NULL when the text does
     * not say. Returning NULL rather than a guess matters: the caller uses
     * this to catch under-declared zones, and a wrong guess on a vague
     * address would overcharge an honest customer.
     */
    function detectDeliveryZone(?string $text): ?string
    {
        $hay = mb_strtolower(trim((string) $text));
        if ($hay === '') {
            return null;
        }

        // ORDER MATTERS — the first zone that matches wins, so the narrow
        // lists come first and 'luzon' is last. That is what keeps "Cagayan
        // de Oro" in Mindanao instead of being caught by Cagayan province.
        $rules = [
            'rizal'    => ['rizal', 'cainta', 'taytay', 'antipolo', 'binangonan', 'angono', 'san mateo', 'rodriguez', 'montalban'],
            'ncr'      => ['metro manila', 'ncr', 'national capital', 'manila', 'quezon city', 'makati', 'pasig', 'taguig',
                           'mandaluyong', 'marikina', 'caloocan', 'paranaque', 'parañaque', 'las pinas', 'las piñas',
                           'muntinlupa', 'pasay', 'valenzuela', 'malabon', 'navotas', 'san juan', 'pateros'],
            'nearby'   => ['bulacan', 'cavite', 'laguna', 'batangas', 'quezon province', 'pampanga'],
            'visayas'  => ['cebu', 'iloilo', 'bacolod', 'negros', 'leyte', 'samar', 'bohol', 'aklan', 'antique',
                           'capiz', 'guimaras', 'siquijor', 'biliran', 'visayas'],
            'mindanao' => ['davao', 'cagayan de oro', 'zamboanga', 'general santos', 'butuan', 'cotabato',
                           'bukidnon', 'misamis', 'agusan', 'surigao', 'lanao', 'sultan kudarat', 'basilan',
                           'sulu', 'tawi-tawi', 'mindanao'],
            'luzon'    => ['luzon', 'ilocos', 'pangasinan', 'la union', 'abra', 'benguet', 'baguio', 'ifugao',
                           'kalinga', 'apayao', 'mountain province', 'cordillera',
                           'cagayan valley', 'tuguegarao', 'aparri', 'isabela', 'nueva vizcaya', 'quirino',
                           'nueva ecija', 'tarlac', 'zambales', 'olongapo', 'bataan', 'aurora',
                           'bicol', 'camarines', 'albay', 'legazpi', 'legaspi', 'sorsogon',
                           'catanduanes', 'masbate',
                           'mindoro', 'calapan', 'marinduque', 'romblon', 'palawan', 'puerto princesa', 'mimaropa'],
        ];

        foreach ($rules as $zone => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($hay, $needle)) {
                    return $zone;
                }
            }
        }
        return null;   // the address does not name a place we recognise
    }

    /**
     * Zone the checkout dropdown should open on. Falls back to the base zone
     * so the customer sees a sensible default rather than a blank.
     */
    function guessDeliveryZone(?string $province, ?string $city = null): string
    {
        return detectDeliveryZone(trim((string) $province . ' ' . (string) $city))
            ?? DELIVERY_ZONE_DEFAULT;
    }

    /**
     * The zone an order is actually charged at.
     *
     * Two cases, deliberately different:
     *
     * 1. The customer has a province saved on their profile. That field is a
     *    clean place name, so it decides the zone outright and the dropdown
     *    is locked to it in the form. The posted value is ignored — a Davao
     *    customer cannot select "Rizal" and pay ₱50 instead of ₱300.
     *
     * 2. No saved province (the customer typed a one-off address). Their
     *    choice stands, because matching keywords against a free-text street
     *    line is unreliable in BOTH directions: "Rizal Street, Davao City"
     *    reads as Rizal, and "Manila East Road, Taytay, Rizal" reads as
     *    Metro Manila. Silently repricing on that basis would overcharge
     *    honest customers. Instead a mismatch is flagged for the admin.
     *
     * @return array{zone: string, fee: float, locked: bool, flag: ?string}
     */
    function resolveDeliveryZone(string $declaredZone, ?string $address, string $method = 'delivery', ?string $profileProvince = null): array
    {
        if ($method === 'pickup') {
            return ['zone' => $declaredZone, 'fee' => 0.00, 'locked' => false, 'flag' => null];
        }

        if (!isDeliveryZone($declaredZone)) {
            $declaredZone = DELIVERY_ZONE_DEFAULT;
        }

        // Case 1 — the profile province decides.
        $fromProfile = detectDeliveryZone($profileProvince);
        if ($fromProfile !== null) {
            return ['zone' => $fromProfile, 'fee' => deliveryFee($fromProfile), 'locked' => true, 'flag' => null];
        }

        // Case 2 — their choice stands; flag an obvious mismatch for review.
        $flag     = null;
        $detected = detectDeliveryZone($address);
        if ($detected !== null && $detected !== $declaredZone && deliveryFee($detected) > deliveryFee($declaredZone)) {
            $flag = 'Address suggests ' . DELIVERY_ZONES[$detected]['label']
                  . ' (₱' . number_format(deliveryFee($detected), 2) . ') but '
                  . DELIVERY_ZONES[$declaredZone]['label'] . ' was selected — please confirm the shipping fee.';
        }
        return ['zone' => $declaredZone, 'fee' => deliveryFee($declaredZone), 'locked' => false, 'flag' => $flag];
    }

    /** Zone table in the shape the checkout JavaScript expects. */
    function deliveryZonesForJs(): array
    {
        $out = [];
        foreach (DELIVERY_ZONES as $key => $z) {
            $out[$key] = (float) $z['fee'];
        }
        return $out;
    }
}
