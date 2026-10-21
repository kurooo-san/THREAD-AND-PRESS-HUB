<?php

/**
 * Duplicate order check for checkout.php.
 *
 * An order counts as a duplicate when the same customer already placed one
 * with exactly the same items (product, colour, size, quantity) within the
 * last DUPLICATE_ORDER_WINDOW_MIN minutes and it was not cancelled. Checkout
 * then asks the customer to confirm instead of blocking: buying the same
 * thing twice can be on purpose.
 */

if (!defined('DUPLICATE_ORDER_WINDOW_MIN')) {

    /** How far back (minutes) an identical order counts as a duplicate. */
    define('DUPLICATE_ORDER_WINDOW_MIN', 10);

    /**
     * Order-independent fingerprint of a list of items, so the same cart in a
     * different order, or the same item split over two lines, still matches.
     */
    function orderItemsSignature(array $items): string
    {
        $lines = [];
        foreach ($items as $it) {
            // order_items rows carry product_id (their 'id' is the line id); cart items carry id.
            $key = (int) ($it['product_id'] ?? $it['id'] ?? 0)
                 . '|' . trim((string) ($it['color'] ?? ''))
                 . '|' . trim((string) ($it['size'] ?? ''));
            $lines[$key] = ($lines[$key] ?? 0) + (int) ($it['quantity'] ?? 0);
        }
        ksort($lines);
        $out = [];
        foreach ($lines as $key => $qty) $out[] = $key . '|' . $qty;
        return implode(';', $out);
    }

    /**
     * The customer's most recent identical, non-cancelled order inside the
     * window as ['id' => int, 'minutes' => int], or null when there is none.
     */
    function findDuplicateOrder(mysqli $conn, int $userId, array $items): ?array
    {
        $want = orderItemsSignature($items);
        if ($want === '') return null;

        // NOW() on the database side, same clock that stamped created_at.
        $stmt = $conn->prepare("SELECT TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) AS minutes, oi.*
                                FROM orders o JOIN order_items oi ON oi.order_id = o.id
                                WHERE o.user_id = ? AND o.status <> 'cancelled'
                                  AND o.created_at >= NOW() - INTERVAL ? MINUTE
                                ORDER BY o.id DESC");
        $window = DUPLICATE_ORDER_WINDOW_MIN;
        $stmt->bind_param('ii', $userId, $window);
        $stmt->execute();
        $res = $stmt->get_result();

        $orders = [];
        while ($row = $res->fetch_assoc()) {
            $oid = (int) $row['order_id'];
            $orders[$oid]['minutes'] = (int) $row['minutes'];
            $orders[$oid]['items'][] = $row;
        }
        $stmt->close();

        foreach ($orders as $oid => $o) {   // newest first
            if (orderItemsSignature($o['items']) === $want) {
                return ['id' => $oid, 'minutes' => max(0, $o['minutes'])];
            }
        }
        return null;
    }
}
