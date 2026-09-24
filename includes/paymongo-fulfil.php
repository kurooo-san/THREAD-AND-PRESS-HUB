<?php

/**
 * PayMongo — turning a confirmed payment into a paid order.
 *
 * This is deliberately the ONLY place that flips an order to paid. The return
 * page and the webhook both call paymongoFulfilSession(), so a customer who
 * never comes back from PayMongo still gets their order marked (webhook), and
 * a customer whose webhook is delayed still sees success (return page) — and
 * neither path can apply a different rule from the other.
 *
 * Requires includes/config.php and includes/paymongo.php first.
 */

require_once __DIR__ . '/paymongo.php';

if (!function_exists('paymongoFulfilSession')) {

    /**
     * Confirm a checkout session against PayMongo and, if it really is paid,
     * mark the matching order paid.
     *
     * Safe to call repeatedly: a session already recorded as paid returns the
     * same success result without touching the order again, so a webhook
     * retry plus the customer's own return cannot double-apply anything.
     *
     * @param string     $sessionId  PayMongo checkout session id.
     * @param array|null $attributes Attributes from a webhook payload. When
     *                               given they are still re-checked against
     *                               the API, because a webhook body is only
     *                               as trustworthy as its signature.
     *
     * @return array{ok:bool, paid:bool, kind:?string, orderId:?int, error:string}
     */
    function paymongoFulfilSession(string $sessionId, ?array $attributes = null): array
    {
        $fail = static fn(string $msg) => ['ok' => false, 'paid' => false, 'kind' => null, 'orderId' => null, 'error' => $msg];

        $row = paymongoFindSession($sessionId);
        if ($row === null) {
            return $fail('That payment session is not one of ours.');
        }

        $kind    = (string) $row['order_kind'];
        $orderId = (int) $row['order_id'];

        // Already settled — nothing left to do.
        if ($row['status'] === 'paid') {
            return ['ok' => true, 'paid' => true, 'kind' => $kind, 'orderId' => $orderId, 'error' => ''];
        }

        // Always ask PayMongo directly. A webhook body tells us WHEN to look,
        // never WHAT to believe.
        $check = paymongoRetrieveCheckoutSession($sessionId);
        if (!$check['ok']) {
            return $fail($check['error'] ?: 'Could not confirm the payment with PayMongo.');
        }

        if (!$check['paid']) {
            return ['ok' => true, 'paid' => false, 'kind' => $kind, 'orderId' => $orderId, 'error' => ''];
        }

        // The amount PayMongo captured must match what we asked for. A
        // mismatch means the session was built for a different total, so it is
        // recorded but NOT allowed to settle the order.
        $expected = paymongoCentavos((float) $row['amount']);
        if ($check['amount'] > 0 && $check['amount'] !== $expected) {
            error_log('[paymongo] amount mismatch on ' . $sessionId . ': expected ' . $expected . ', got ' . $check['amount']);
            paymongoUpdateSessionStatus($sessionId, 'failed', $check['paymentId'], $check['method']);
            return $fail('The paid amount does not match this order. Please contact support.');
        }

        $applied = $kind === 'custom'
            ? paymongoApplyCustomOrderPaid($orderId, $sessionId, $check)
            : paymongoApplyOrderPaid($orderId, $sessionId, $check);

        if (!$applied) {
            return $fail('Payment received but the order could not be updated. Please contact support.');
        }

        paymongoUpdateSessionStatus($sessionId, 'paid', $check['paymentId'], $check['method']);
        return ['ok' => true, 'paid' => true, 'kind' => $kind, 'orderId' => $orderId, 'error' => ''];
    }

    /**
     * Mark a normal shop order paid.
     *
     * payment_status goes straight to 'verified': PayMongo already collected
     * and confirmed the money, so there is nothing for an admin to eyeball
     * the way the old screenshot-upload flow needed.
     */
    function paymongoApplyOrderPaid(int $orderId, string $sessionId, array $check): bool
    {
        global $conn;

        $reference = $check['paymentId'] ?: $sessionId;

        $stmt = $conn->prepare(
            "UPDATE orders
                SET payment_method  = 'paymongo',
                    payment_status  = 'verified',
                    payment_reference = ?,
                    status = CASE WHEN status = 'pending' THEN 'confirmed' ELSE status END
              WHERE id = ?"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $reference, $orderId);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok && function_exists('logAudit')) {
            logAudit('paymongo_payment_verified', 'order', $orderId,
                'Paid via PayMongo (' . ($check['method'] ?: 'unknown') . ') ref ' . $reference);
        }
        return $ok;
    }

    /**
     * Mark a custom-design order paid.
     *
     * The custom flow records money in custom_order_payments rather than on
     * the order row, so insert a verified row (or update the one already
     * there) and move the order to payment_verified.
     */
    function paymongoApplyCustomOrderPaid(int $orderId, string $sessionId, array $check): bool
    {
        global $conn;

        $reference = $check['paymentId'] ?: $sessionId;

        $amtStmt = $conn->prepare("SELECT total_price FROM custom_orders WHERE id = ?");
        if (!$amtStmt) {
            return false;
        }
        $amtStmt->bind_param('i', $orderId);
        $amtStmt->execute();
        $amountRow = $amtStmt->get_result()->fetch_assoc();
        $amtStmt->close();
        if (!$amountRow) {
            return false;
        }
        $amount = (float) $amountRow['total_price'];

        $existing = $conn->prepare("SELECT id FROM custom_order_payments WHERE custom_order_id = ? LIMIT 1");
        $existing->bind_param('i', $orderId);
        $existing->execute();
        $found = $existing->get_result()->fetch_assoc();
        $existing->close();

        if ($found) {
            $stmt = $conn->prepare(
                "UPDATE custom_order_payments
                    SET payment_method = 'paymongo', reference_number = ?, amount = ?,
                        payment_status = 'verified'
                  WHERE id = ?"
            );
            if (!$stmt) {
                return false;
            }
            $pid = (int) $found['id'];
            $stmt->bind_param('sdi', $reference, $amount, $pid);
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO custom_order_payments (custom_order_id, payment_method, reference_number, amount, payment_status)
                 VALUES (?, 'paymongo', ?, ?, 'verified')"
            );
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('isd', $orderId, $reference, $amount);
        }
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $up = $conn->prepare("UPDATE custom_orders SET status = 'payment_verified' WHERE id = ? AND status = 'pending_payment'");
            if ($up) {
                $up->bind_param('i', $orderId);
                $up->execute();
                $up->close();
            }
            if (function_exists('logAudit')) {
                logAudit('paymongo_payment_verified', 'custom_order', $orderId,
                    'Paid via PayMongo (' . ($check['method'] ?: 'unknown') . ') ref ' . $reference);
            }
        }
        return $ok;
    }
}
