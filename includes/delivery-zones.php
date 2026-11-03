<?php

/**
 * Delivery zones and their fees.
 *
 * The store ships from Cainta, Rizal, so Rizal is the base rate and every
 * other zone is priced by distance from it.
 *
 * Fees and delivery days are set by the admin in Admin > Shipping Rates
 * (saved to DELIVERY_RATES_FILE); the values below are the defaults used
 * until then. Nothing else in the codebase hardcodes a delivery amount —
 * checkout.php reads the fee from DELIVERY_ZONES on the server, and hands
 * the same table to the browser so the order summary and the recorded total
 * can never disagree.
 *
 * Requires includes/config.php first (for sanitizeInput()).
 */

if (!defined('DELIVERY_ZONES')) {

    define('DELIVERY_ZONE_DEFAULTS', [
        // 'days' = [min, max] working days (Sundays skipped) for the delivery estimate.
        'rizal'     => ['label' => 'Rizal (Cainta, Taytay, Antipolo, nearby)', 'fee' => 50.00,  'days' => [1, 2]],
        'ncr'       => ['label' => 'Metro Manila / NCR',                        'fee' => 100.00, 'days' => [1, 2]],
        'nearby'    => ['label' => 'Bulacan, Cavite, Laguna, Batangas, Quezon, Pampanga', 'fee' => 150.00, 'days' => [2, 3]],
        'luzon'     => ['label' => 'Rest of Luzon',                             'fee' => 200.00, 'days' => [3, 5]],
        'visayas'   => ['label' => 'Visayas',                                   'fee' => 250.00, 'days' => [3, 5]],
        'mindanao'  => ['label' => 'Mindanao',                                  'fee' => 300.00, 'days' => [3, 5]],
    ]);

    /** Admin-set fees and days. storage/payment-config/ is on the Railway volume and blocked from the web. */
    define('DELIVERY_RATES_FILE', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR
        . 'payment-config' . DIRECTORY_SEPARATOR . 'shipping-rates.json');
    define('DELIVERY_FEE_MAX', 10000.00);
    define('DELIVERY_DAYS_MAX', 30);

    /** Error message for a bad fee / day range, or NULL when it is fine. */
    function deliveryRateError($fee, $minDays, $maxDays): ?string
    {
        if (!is_numeric($fee) || (float) $fee < 0 || (float) $fee > DELIVERY_FEE_MAX) {
            return 'Fees must be between ₱0 and ₱' . number_format(DELIVERY_FEE_MAX) . '.';
        }
        if (filter_var($minDays, FILTER_VALIDATE_INT) === false || filter_var($maxDays, FILTER_VALIDATE_INT) === false
            || (int) $minDays < 1 || (int) $maxDays > DELIVERY_DAYS_MAX || (int) $minDays > (int) $maxDays) {
            return 'Delivery days must be whole numbers from 1 to ' . DELIVERY_DAYS_MAX . ', and "from" cannot be more than "to".';
        }
        return null;
    }

    /**
     * The zone table: defaults with the admin's saved fee/days on top. A
     * missing, unreadable or tampered file (or one bad zone in it) falls back
     * to the defaults for that zone, so checkout can never be priced off junk.
     */
    function deliveryZonesLoad(): array
    {
        $zones = DELIVERY_ZONE_DEFAULTS;
        $raw   = is_file(DELIVERY_RATES_FILE) ? @file_get_contents(DELIVERY_RATES_FILE) : false;
        $saved = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($saved)) {
            return $zones;
        }
        foreach ($zones as $key => $zone) {
            $r = $saved[$key] ?? null;
            if (is_array($r) && isset($r['fee'], $r['days'][0], $r['days'][1])
                && deliveryRateError($r['fee'], $r['days'][0], $r['days'][1]) === null) {
                $zones[$key]['fee']  = round((float) $r['fee'], 2);
                $zones[$key]['days'] = [(int) $r['days'][0], (int) $r['days'][1]];
            }
        }
        return $zones;
    }

    /** Save [zone => ['fee' => float, 'days' => [min, max]]]; the caller validates first. */
    function deliveryRatesSave(array $rates): bool
    {
        $dir = dirname(DELIVERY_RATES_FILE);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        $json = json_encode($rates, JSON_PRETTY_PRINT);
        return $json !== false && file_put_contents(DELIVERY_RATES_FILE, $json, LOCK_EX) !== false;
    }

    define('DELIVERY_ZONES', deliveryZonesLoad());

    /** Working days until a store-pickup order is ready. */
    define('PICKUP_READY_DAYS', 1);

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

    /**
     * Estimated arrival for an order placed at $from (default now): the
     * zone's day range counted in working days, Sundays skipped.
     *
     * @return array{from: int, to: int, label: string}
     */
    function estimatedDelivery(string $zone, string $method = 'delivery', ?int $from = null): array
    {
        $from = $from ?? time();
        if ($method === 'pickup') {
            $min = $max = PICKUP_READY_DAYS;
        } else {
            [$min, $max] = DELIVERY_ZONES[isDeliveryZone($zone) ? $zone : DELIVERY_ZONE_DEFAULT]['days'];
        }

        $addDays = static function (int $n) use ($from): int {
            $t = $from;
            while ($n > 0) {
                $t = strtotime('+1 day', $t);
                if (date('w', $t) !== '0') $n--;
            }
            return $t;
        };
        $a = $addDays($min);
        $b = $addDays($max);
        $label = date('M j', $a) === date('M j', $b) ? date('M j', $a) : date('M j', $a) . ' – ' . date('M j', $b);
        return ['from' => $a, 'to' => $b, 'label' => $label];
    }

    /**
     * Zone an existing order was charged at, from its full (pre-voucher)
     * delivery fee, falling back to the address text. NULL when neither says.
     * orders has no zone column; the fee is what the customer actually paid for.
     */
    function deliveryZoneOfOrder(float $fullFee, ?string $address): ?string
    {
        $matches = [];
        foreach (DELIVERY_ZONES as $key => $z) {
            if (abs((float) $z['fee'] - $fullFee) < 0.01) $matches[] = $key;
        }
        // The admin can give two areas the same fee; the address then picks between them.
        $fromAddress = detectDeliveryZone($address);
        if (count($matches) > 1 && in_array($fromAddress, $matches, true)) return $fromAddress;
        return $matches[0] ?? $fromAddress;
    }

    /** "Shipping fee by area, from ₱50" for the home page and footer, from the lowest fee. */
    function deliveryFeeFromText(): string
    {
        $min = (float) min(array_column(DELIVERY_ZONES, 'fee'));
        if ($min <= 0) return 'Shipping fee by area';
        return 'Shipping fee by area, from ₱' . number_format($min, floor($min) == $min ? 0 : 2);
    }

    /**
     * Ready-to-print estimate for an order row (needs created_at, status,
     * delivery_fee, delivery_address, optional shipping_discount), or NULL
     * when the order is finished/cancelled or its zone cannot be told.
     */
    function orderDeliveryEstimate(array $order): ?string
    {
        if (in_array($order['status'] ?? '', ['completed', 'cancelled'], true)) return null;
        $placed = strtotime((string) ($order['created_at'] ?? '')) ?: time();
        if (($order['delivery_address'] ?? '') === 'Store Pickup') {
            return 'Ready for pickup by ' . estimatedDelivery('', 'pickup', $placed)['label'];
        }
        $zone = deliveryZoneOfOrder(
            (float) ($order['delivery_fee'] ?? 0) + (float) ($order['shipping_discount'] ?? 0),
            $order['delivery_address'] ?? null
        );
        return $zone === null ? null : 'Estimated delivery: ' . estimatedDelivery($zone, 'delivery', $placed)['label'];
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
