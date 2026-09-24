<?php

/**
 * PayMongo — start a hosted checkout for an order.
 *
 * Reached from checkout.php (shop orders) and custom-payment.php (custom
 * design orders). Builds the line items FROM THE DATABASE, opens a PayMongo
 * checkout session, and redirects the customer to PayMongo's page.
 *
 * Nothing about the amount comes from the request: the URL carries only an
 * order id, and the customer must own that order.
 */

require 'includes/config.php';
require_once 'includes/paymongo.php';
redirectToLogin();

$pageTitle = 'Redirecting to payment';

$userId  = (int) $_SESSION['user_id'];
$kind    = ($_GET['kind'] ?? 'order') === 'custom' ? 'custom' : 'order';
$orderId = (int) ($_GET['order_id'] ?? 0);
$error   = '';

/** Send the customer back where they came from, with a readable reason. */
function paymongoBail(string $kind, int $orderId, string $message): void
{
    $_SESSION['error'] = $message;
    $back = $kind === 'custom'
        ? ($orderId > 0 ? 'custom-order-tracking.php?order_id=' . $orderId : 'my-custom-orders.php')
        : ($orderId > 0 ? 'order_details.php?id=' . $orderId : 'orders.php');
    header('Location: ' . $back);
    exit;
}

if ($orderId <= 0) {
    paymongoBail($kind, 0, 'No order was specified for payment.');
}

if (!paymongoIsConfigured()) {
    paymongoBail($kind, $orderId, 'Online payment is temporarily unavailable. Please try again later or contact support.');
}

if (!paymongoTableExists()) {
    error_log('[paymongo] migrate_paymongo.sql has not been applied');
    paymongoBail($kind, $orderId, 'Online payment is not set up yet. Please contact support.');
}

// ---------------------------------------------------------------------
// Load the order, prove ownership, and build the line items.
// ---------------------------------------------------------------------
$lineItems   = [];
$amountDue   = 0.00;
$description = '';
$reference   = '';

if ($kind === 'custom') {
    $stmt = $conn->prepare(
        "SELECT co.id, co.user_id, co.product_type, co.size, co.apparel_color, co.quantity,
                co.total_price, co.status
           FROM custom_orders co
          WHERE co.id = ? AND co.user_id = ?
          LIMIT 1"
    );
    $stmt->bind_param('ii', $orderId, $userId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        paymongoBail($kind, $orderId, 'That custom order was not found.');
    }
    if (!in_array($order['status'], ['pending_payment', 'payment_uploaded'], true)) {
        paymongoBail($kind, $orderId, 'That custom order is not awaiting payment.');
    }

    // Already settled? Don't let them pay twice.
    $paidCheck = $conn->prepare("SELECT id FROM custom_order_payments WHERE custom_order_id = ? AND payment_status = 'verified' LIMIT 1");
    $paidCheck->bind_param('i', $orderId);
    $paidCheck->execute();
    $alreadyPaid = $paidCheck->get_result()->num_rows > 0;
    $paidCheck->close();
    if ($alreadyPaid) {
        paymongoBail($kind, $orderId, 'That custom order is already paid.');
    }

    $amountDue   = (float) $order['total_price'];
    $description = 'Custom design order #' . $orderId;
    $reference   = 'TPH-CUSTOM-' . $orderId;

    // One line for the whole custom job: the price is a single quoted total
    // (base + print + colour, less any discount), not a per-item breakdown.
    $lineItems[] = [
        'name'        => 'Custom ' . ucfirst((string) $order['product_type']),
        'amount'      => paymongoCentavos($amountDue),
        'quantity'    => 1,
        'description' => trim(sprintf(
            '%s · Size %s · Qty %d',
            (string) $order['apparel_color'],
            (string) $order['size'],
            (int) $order['quantity']
        ), ' ·'),
    ];
} else {
    $stmt = $conn->prepare(
        "SELECT id, user_id, subtotal, discount_amount, coupon_discount, delivery_fee, total,
                payment_status, status
           FROM orders
          WHERE id = ? AND user_id = ?
          LIMIT 1"
    );
    $stmt->bind_param('ii', $orderId, $userId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        paymongoBail($kind, $orderId, 'That order was not found.');
    }
    if ($order['payment_status'] === 'verified') {
        paymongoBail($kind, $orderId, 'That order is already paid.');
    }
    if ($order['status'] === 'cancelled') {
        paymongoBail($kind, $orderId, 'That order was cancelled and can no longer be paid.');
    }

    $amountDue   = (float) $order['total'];
    $description = 'Order #' . $orderId . ' — Thread & Press Hub';
    $reference   = 'TPH-' . $orderId;

    $totalCentavos = paymongoCentavos($amountDue);

    // Preferred: a real per-product breakdown on the PayMongo page. It is only
    // usable when the individual lines add up to the stored total exactly —
    // PayMongo has no concept of a negative line, so a discounted order can
    // never be represented item by item.
    $discounts = (float) $order['discount_amount'] + (float) ($order['coupon_discount'] ?? 0);

    if ($discounts <= 0.0049) {
        $detailed = [];
        $sum      = 0;

        $itemsStmt = $conn->prepare(
            "SELECT p.name, oi.quantity, oi.unit_price, oi.color, oi.size
               FROM order_items oi
               JOIN products p ON p.id = oi.product_id
              WHERE oi.order_id = ?"
        );
        $itemsStmt->bind_param('i', $orderId);
        $itemsStmt->execute();
        $itemsRes = $itemsStmt->get_result();
        while ($it = $itemsRes->fetch_assoc()) {
            $unit = paymongoCentavos((float) $it['unit_price']);
            $qty  = max(1, (int) $it['quantity']);
            $sum += $unit * $qty;
            $bits = array_filter([$it['color'] ?? '', $it['size'] ?? '']);
            $detailed[] = [
                'name'        => (string) $it['name'],
                'amount'      => $unit,
                'quantity'    => $qty,
                'description' => $bits ? implode(' · ', $bits) : null,
            ];
        }
        $itemsStmt->close();

        $vat = $totalCentavos - $sum - paymongoCentavos((float) $order['delivery_fee']);
        if ($vat > 0) {
            $detailed[] = ['name' => 'VAT (12%)', 'amount' => $vat, 'quantity' => 1];
            $sum += $vat;
        }
        $fee = paymongoCentavos((float) $order['delivery_fee']);
        if ($fee > 0) {
            $detailed[] = ['name' => 'Delivery fee', 'amount' => $fee, 'quantity' => 1];
            $sum += $fee;
        }

        // Only trust the breakdown if it reconciles to the cent.
        if ($detailed !== [] && $sum === $totalCentavos) {
            $lineItems = $detailed;
        }
    }

    // Fallback: charge the stored total as one line. Always exact.
    if ($lineItems === []) {
        $lineItems[] = [
            'name'        => 'Order #' . $orderId,
            'amount'      => $totalCentavos,
            'quantity'    => 1,
            'description' => 'Thread & Press Hub order total',
        ];
    }
}

if ($amountDue <= 0) {
    paymongoBail($kind, $orderId, 'That order has nothing left to pay.');
}

// PayMongo rejects anything under ₱20.00.
if (paymongoCentavos($amountDue) < 2000) {
    paymongoBail($kind, $orderId, 'Online payment needs a total of at least ₱20.00. Please choose cash instead.');
}

// ---------------------------------------------------------------------
// Reuse a live session, or open a new one.
// ---------------------------------------------------------------------
// Compared in centavos: two DECIMAL-to-float values that look identical can
// still fail a float ===, which would quietly open a new session every time.
$existing = paymongoReusableSession($kind, $orderId);
if ($existing
    && paymongoCentavos((float) $existing['amount']) === paymongoCentavos($amountDue)
    && !empty($existing['checkout_url'])) {
    header('Location: ' . $existing['checkout_url']);
    exit;
}

$base       = paymongoBaseUrl();
$successUrl = $base . '/paymongo-return.php?kind=' . $kind . '&order_id=' . $orderId . '&result=success';
$cancelUrl  = $base . '/paymongo-return.php?kind=' . $kind . '&order_id=' . $orderId . '&result=cancel';

// Prefill the payer details from the profile so the PayMongo form is shorter.
$billing = [];
$uStmt = $conn->prepare("SELECT fullname, email, phone FROM users WHERE id = ? LIMIT 1");
if ($uStmt) {
    $uStmt->bind_param('i', $userId);
    $uStmt->execute();
    if ($u = $uStmt->get_result()->fetch_assoc()) {
        $billing = ['name' => $u['fullname'] ?? '', 'email' => $u['email'] ?? '', 'phone' => $u['phone'] ?? ''];
    }
    $uStmt->close();
}

$session = paymongoCreateCheckoutSession(
    $lineItems,
    $successUrl,
    $cancelUrl,
    $reference,
    $description,
    $billing
);

if (!$session['ok']) {
    error_log('[paymongo] could not open a session for ' . $kind . ' #' . $orderId . ': ' . $session['error']);
    paymongoBail($kind, $orderId, 'We could not start the online payment: ' . $session['error']);
}

paymongoRecordSession($session['id'], $kind, $orderId, $userId, $amountDue, $session['url']);

header('Location: ' . $session['url']);
exit;
