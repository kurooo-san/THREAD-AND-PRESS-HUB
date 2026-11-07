<?php

/**
 * Courier + tracking for shop orders.
 *
 * The shop books the parcel itself (no courier API: that needs a business
 * account), then records who carries it. Marking a delivery order "Out for
 * Delivery" requires these details, and the customer sees them on the order
 * page, in the status email and through the chatbot. A courier API could
 * later fill the same columns automatically.
 *
 * Requires includes/config.php first ($conn).
 */

if (!defined('COURIERS')) {

    // 'track' = the courier's public tracking page; the number is appended (urlencoded).
    // shortcut: these are the couriers' own web pages, not an API, so if a courier changes
    // its URL the Copy button still works; update the URL here when that happens.
    define('COURIERS', [
        'jnt'      => ['label' => 'J&T Express',   'track' => 'https://www.jtexpress.ph/trajectoryQuery?flag=1&waybillNo='],
        'flash'    => ['label' => 'Flash Express', 'track' => 'https://www.flashexpress.ph/fle/tracking?se='],
        'ninjavan' => ['label' => 'Ninja Van',     'track' => 'https://www.ninjavan.co/en-ph/tracking?id='],
        'lbc'      => ['label' => 'LBC Express',   'track' => 'https://www.lbcexpress.com/track/?tracking_no='],
        // On-demand couriers give a share link instead of a waybill number.
        'lalamove' => ['label' => 'Lalamove',      'track' => null],
        'grab'     => ['label' => 'Grab Express',  'track' => null],
        'rider'    => ['label' => 'Own Rider',     'track' => null],
    ]);

    /**
     * Adds the shipment columns to orders if missing. Call it before reading
     * or writing them (it is cheap after the first call per request).
     */
    function orderShipmentReady(): bool
    {
        global $conn;
        static $ready = null;
        if ($ready !== null) return $ready;
        $cols = [
            'courier'         => "VARCHAR(20) NULL DEFAULT NULL",
            'tracking_number' => "VARCHAR(300) NULL DEFAULT NULL",
            'rider_name'      => "VARCHAR(80) NULL DEFAULT NULL",
            'rider_phone'     => "VARCHAR(20) NULL DEFAULT NULL",
        ];
        foreach ($cols as $col => $def) {
            $r = $conn->query("SHOW COLUMNS FROM orders LIKE '$col'");
            if ($r && $r->num_rows === 0 && !$conn->query("ALTER TABLE orders ADD COLUMN `$col` $def")) {
                error_log('[couriers] could not add orders.' . $col . ': ' . $conn->error);
                return $ready = false;
            }
        }
        return $ready = true;
    }

    /** True for an order that is delivered (not Store Pickup), i.e. needs a courier. */
    function orderNeedsCourier(array $order): bool
    {
        return ($order['delivery_address'] ?? '') !== 'Store Pickup';
    }

    /**
     * Checks posted shipment details.
     *
     * @return array{0: ?array, 1: ?string} [clean values, null] or [null, error message]
     */
    function shipmentFromInput(array $in): array
    {
        $courier  = (string) ($in['courier'] ?? '');
        $tracking = trim((string) ($in['tracking_number'] ?? ''));
        $name     = trim(preg_replace('/\s+/u', ' ', (string) ($in['rider_name'] ?? '')) ?? '');
        $phone    = preg_replace('/[\s()-]/', '', (string) ($in['rider_phone'] ?? '')) ?? '';

        if (!isset(COURIERS[$courier])) {
            return [null, 'Choose the courier.'];
        }
        if ($tracking !== '' && !shipmentTrackingValid($tracking)) {
            return [null, 'Tracking number: use letters, numbers and dashes only (4-40 characters), or paste the https:// tracking link.'];
        }
        if ($courier === 'rider') {
            if ($name === '' || mb_strlen($name) > 80 || preg_match('/[\x00-\x1F\x7F<>]/u', $name) || !mb_check_encoding($name, 'UTF-8')) {
                return [null, "Enter the rider's name (up to 80 characters)."];
            }
            if (!preg_match('/^(09\d{9}|\+639\d{9})$/', $phone)) {
                return [null, "Enter the rider's mobile number, e.g. 09171234567."];
            }
        } else {
            if ($tracking === '') {
                return [null, COURIERS[$courier]['track'] === null
                    ? 'Paste the tracking link (or booking number) from ' . COURIERS[$courier]['label'] . '.'
                    : 'Enter the ' . COURIERS[$courier]['label'] . ' tracking number.'];
            }
            $name = $phone = '';   // rider details only belong to Own Rider
        }
        return [[
            'courier'         => $courier,
            'tracking_number' => $tracking === '' ? null : $tracking,
            'rider_name'      => $name === '' ? null : $name,
            'rider_phone'     => $phone === '' ? null : $phone,
        ], null];
    }

    /** A waybill/booking number, or an https link (Lalamove / Grab share links). */
    function shipmentTrackingValid(string $t): bool
    {
        if (preg_match('/^[A-Za-z0-9-]{4,40}$/', $t)) return true;
        return strlen($t) <= 300
            && filter_var($t, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($t, PHP_URL_SCHEME)) === 'https';
    }

    /** The shop's own tracking number: built from the order id, so it is unique and never changes. */
    function generatedTrackingNumber(int $orderId): string
    {
        return 'TP-' . str_pad((string) $orderId, 8, '0', STR_PAD_LEFT);
    }

    /**
     * What to show the customer, or NULL when the order has no courier yet.
     *
     * @return array{courier: string, number: ?string, url: ?string, rider: ?string, phone: ?string}|null
     */
    function orderShipment(array $order): ?array
    {
        $key = (string) ($order['courier'] ?? '');
        if (!isset(COURIERS[$key])) return null;
        $t = (string) ($order['tracking_number'] ?? '');
        // Re-checked on output: only an https link or a plain number ever becomes a link.
        $isLink = $t !== '' && shipmentTrackingValid($t) && !preg_match('/^[A-Za-z0-9-]{4,40}$/', $t);
        $url = null;
        if ($isLink) {
            $url = $t;
        // The courier's site does not know the shop's own TP- numbers, so they get no link.
        } elseif ($t !== '' && COURIERS[$key]['track'] !== null && shipmentTrackingValid($t) && !preg_match('/^TP-\d{8,}$/', $t)) {
            $url = COURIERS[$key]['track'] . rawurlencode($t);
        }
        return [
            'courier' => COURIERS[$key]['label'],
            'number'  => ($t !== '' && !$isLink) ? $t : null,
            'url'     => $url,
            'rider'   => ($order['rider_name'] ?? '') !== '' ? (string) $order['rider_name'] : null,
            'phone'   => ($order['rider_phone'] ?? '') !== '' ? (string) $order['rider_phone'] : null,
        ];
    }

    /** One line for the chatbot and emails, e.g. "J&T Express, tracking no. 1234". */
    function orderShipmentText(array $order): ?string
    {
        $s = orderShipment($order);
        if ($s === null) return null;
        $parts = [$s['courier']];
        if ($s['rider'])  $parts[] = 'rider ' . $s['rider'] . ($s['phone'] ? ' (' . $s['phone'] . ')' : '');
        if ($s['number']) $parts[] = 'tracking no. ' . $s['number'];
        if ($s['url'])    $parts[] = 'track at ' . $s['url'];
        return implode(', ', $parts);
    }
}
