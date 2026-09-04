<?php
declare(strict_types=1);

/**
 * Legacy Maya payment page — superseded by the unified, config-driven
 * manual-QR checkout (payment-qr.php), which reads the InstaPay/GCash/Maya
 * channels from admin Payment Settings instead of hardcoded values.
 *
 * Kept as a redirect so old links, bookmarks, and emails still work.
 */

require __DIR__ . '/includes/config.php';
redirectToLogin();

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$target  = $orderId > 0 ? 'payment-qr.php?order_id=' . $orderId : 'orders.php';

header('Location: ' . $target, true, 302);
exit;
