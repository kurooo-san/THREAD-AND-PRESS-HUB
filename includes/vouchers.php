<?php
// Voucher wallet: admin gives a coupon to customers, customers pick from what
// they were given at checkout (no typed codes). A coupon is either a
// 'discount' (off the items) or 'shipping' (off the delivery fee); one of each
// may be used per order.
//
// couponCheck() and voucherBestPick() are pure so tests/VoucherTest.php can
// cover the money rules without a database.

/**
 * Can this coupon be used right now, and how much does it take off?
 * Discount vouchers come off the item subtotal, shipping vouchers off the fee.
 * Returns ['ok'=>bool, 'message'=>string, 'discount'=>float, 'short'=>float]
 * where 'short' is how much more subtotal is needed to unlock it (else 0).
 */
function couponCheck(array $coupon, float $subtotal, float $deliveryFee = 0.0, ?int $now = null): array {
    $now  = $now ?? time();
    $fail = fn($msg, $short = 0.0) => ['ok' => false, 'message' => $msg, 'discount' => 0.0, 'short' => $short];

    if ((int)($coupon['is_active'] ?? 0) !== 1)                                   return $fail('This voucher is not active.');
    if (!empty($coupon['valid_from'])  && strtotime($coupon['valid_from'])  > $now) return $fail('This voucher is not yet valid.');
    if (!empty($coupon['valid_until']) && strtotime($coupon['valid_until']) < $now) return $fail('This voucher has expired.');
    if (isset($coupon['max_uses']) && (int)$coupon['times_used'] >= (int)$coupon['max_uses']) return $fail('This voucher has been fully used up.');

    $min = (float)($coupon['min_subtotal'] ?? 0);
    if ($subtotal < $min) {
        return $fail('Minimum subtotal for this voucher is ₱' . number_format($min, 2) . '.', round($min - $subtotal, 2));
    }

    $isShipping = ($coupon['kind'] ?? 'discount') === 'shipping';
    if ($isShipping && $deliveryFee <= 0) {
        return $fail('Free-shipping vouchers only work on delivery orders.');
    }
    $base  = $isShipping ? $deliveryFee : $subtotal;
    $value = (float)$coupon['discount_value'];
    $discount = ($coupon['discount_type'] === 'percent')
        ? round($base * $value / 100, 2)
        : min($value, $base);

    return ['ok' => true, 'message' => 'Voucher applied.', 'discount' => round($discount, 2), 'short' => 0.0];
}

/**
 * The wallet id (user_coupons.id) of the voucher of $kind that saves the most
 * right now, or 0 when none is usable. Ties go to the one expiring soonest so
 * the customer doesn't waste a voucher about to lapse.
 */
function voucherBestPick(array $vouchers, string $kind, float $subtotal, float $deliveryFee, ?int $now = null): int {
    $best = 0; $bestSave = 0.0; $bestEnd = PHP_INT_MAX;
    foreach ($vouchers as $v) {
        if (($v['kind'] ?? 'discount') !== $kind) continue;
        $r = couponCheck($v, $subtotal, $deliveryFee, $now);
        if (!$r['ok'] || $r['discount'] <= 0) continue;
        $end = !empty($v['valid_until']) ? strtotime($v['valid_until']) : PHP_INT_MAX;
        if ($r['discount'] > $bestSave + 0.001 || (abs($r['discount'] - $bestSave) <= 0.001 && $end < $bestEnd)) {
            $best = (int)$v['wallet_id']; $bestSave = $r['discount']; $bestEnd = $end;
        }
    }
    return $best;
}

// ---------------------------------------------------------------------------
// Database side
// ---------------------------------------------------------------------------

/**
 * Creates the wallet table and the extra columns on first use, so Railway
 * needs no manual migration. False when coupons are not set up at all.
 */
function vouchersReady(): bool {
    global $conn;
    static $ready = null;
    if ($ready !== null) return $ready;
    if (!couponsTableExists()) return $ready = false;

    try {
        $conn->query("CREATE TABLE IF NOT EXISTS `user_coupons` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `coupon_id` int(11) NOT NULL,
            `note` varchar(255) DEFAULT NULL,
            `given_by` int(11) DEFAULT NULL,
            `given_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `used_at` datetime DEFAULT NULL,
            `order_id` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_user_coupon` (`user_id`, `coupon_id`),
            KEY `idx_coupon` (`coupon_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if ($conn->query("SHOW COLUMNS FROM coupons LIKE 'kind'")->num_rows === 0) {
            $conn->query("ALTER TABLE coupons ADD COLUMN `kind` ENUM('discount','shipping') NOT NULL DEFAULT 'discount' AFTER `description`");
        }
        if ($conn->query("SHOW COLUMNS FROM orders LIKE 'shipping_coupon_code'")->num_rows === 0) {
            $conn->query("ALTER TABLE orders
                          ADD COLUMN `shipping_coupon_code` VARCHAR(50) DEFAULT NULL,
                          ADD COLUMN `shipping_discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('[vouchers] schema: ' . $e->getMessage());
        return $ready = false;
    }
    return $ready = true;
}

/**
 * A customer's vouchers with the coupon details, newest gift first.
 * $unusedOnly limits it to ones not yet spent on an order.
 */
function voucherWallet(int $userId, bool $unusedOnly = true): array {
    global $conn;
    if (!vouchersReady()) return [];
    $sql = "SELECT uc.id AS wallet_id, uc.note, uc.given_at, uc.used_at, uc.order_id, c.*
              FROM user_coupons uc
              JOIN coupons c ON c.id = uc.coupon_id
             WHERE uc.user_id = ?" . ($unusedOnly ? " AND uc.used_at IS NULL" : "") . "
             ORDER BY uc.given_at DESC, uc.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** One unused voucher of $kind from this user's own wallet, or null (forged ids land here). */
function voucherGet(int $userId, int $walletId, string $kind): ?array {
    foreach (voucherWallet($userId) as $v) {
        if ((int)$v['wallet_id'] === $walletId && $v['kind'] === $kind) return $v;
    }
    return null;
}

/**
 * Marks a wallet voucher as spent on $orderId and claims one global use of the
 * coupon. Call inside the checkout transaction; false means it was already
 * used (double submit, two tabs) or the coupon ran out.
 */
function voucherClaim(int $userId, array $voucher, int $orderId): bool {
    global $conn;
    $walletId = (int)$voucher['wallet_id'];
    $stmt = $conn->prepare("UPDATE user_coupons SET used_at = NOW(), order_id = ?
                             WHERE id = ? AND user_id = ? AND used_at IS NULL");
    $stmt->bind_param("iii", $orderId, $walletId, $userId);
    $stmt->execute();
    $ok = $stmt->affected_rows === 1;
    $stmt->close();
    return $ok && incrementCouponUsage((int)$voucher['id']);
}

/**
 * Puts the vouchers spent on a cancelled order back in the customer's wallet
 * and frees their coupon use. Safe to call twice: a returned voucher no longer
 * points at the order. Returns how many came back.
 */
function voucherRelease(int $orderId): int {
    global $conn;
    if (!vouchersReady()) return 0;
    $stmt = $conn->prepare("SELECT id, coupon_id FROM user_coupons WHERE order_id = ? AND used_at IS NOT NULL");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $back = $conn->prepare("UPDATE user_coupons SET used_at = NULL, order_id = NULL WHERE id = ? AND order_id = ?");
    $free = $conn->prepare("UPDATE coupons SET times_used = times_used - 1 WHERE id = ? AND times_used > 0");
    $n = 0;
    foreach ($rows as $r) {
        $back->bind_param("ii", $r['id'], $orderId);
        $back->execute();
        if ($back->affected_rows !== 1) continue;
        $free->bind_param("i", $r['coupon_id']);
        $free->execute();
        $n++;
    }
    $back->close();
    $free->close();
    return $n;
}

/**
 * Gives a coupon to customers. $userIds is a list of ids, or null for every
 * customer (admins excluded). Someone who already holds it is skipped, so
 * giving twice never doubles a voucher. Returns the ids that newly got it.
 */
function voucherGive(int $couponId, ?array $userIds, string $note, int $adminId): array {
    global $conn;
    if (!vouchersReady()) return [];

    if ($userIds === null) {
        $res = $conn->query("SELECT id FROM users WHERE user_type <> 'admin'");
        $userIds = array_map('intval', array_column($res->fetch_all(MYSQLI_ASSOC), 'id'));
    }
    $note = trim($note) === '' ? null : mb_substr(trim($note), 0, 255);

    $given = [];
    $stmt = $conn->prepare("INSERT IGNORE INTO user_coupons (user_id, coupon_id, note, given_by)
                            SELECT id, ?, ?, ? FROM users WHERE id = ? AND user_type <> 'admin'");
    foreach (array_unique(array_map('intval', $userIds)) as $uid) {
        $stmt->bind_param("isii", $couponId, $note, $adminId, $uid);
        $stmt->execute();
        if ($stmt->affected_rows === 1) $given[] = $uid;
    }
    $stmt->close();
    return $given;
}

/** "10% off", "₱50 off", "Free shipping", "50% off shipping" — for cards and emails. */
function voucherLabel(array $c): string {
    $v = (float)$c['discount_value'];
    $amount = $c['discount_type'] === 'percent'
        ? rtrim(rtrim(number_format($v, 2), '0'), '.') . '%'
        : '₱' . rtrim(rtrim(number_format($v, 2), '0'), '.');
    if (($c['kind'] ?? 'discount') === 'shipping') {
        return ($c['discount_type'] === 'percent' && $v >= 100) ? 'Free shipping' : $amount . ' off shipping';
    }
    return $amount . ' off';
}
