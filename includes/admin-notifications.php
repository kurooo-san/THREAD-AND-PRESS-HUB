<?php
// Admin notifications poll endpoint — returns counts as JSON.
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit();
}

$last_seen = isset($_GET['since']) ? (int)$_GET['since'] : 0; // unix ts; default 0 means "all time"
$since_dt  = $last_seen > 0 ? date('Y-m-d H:i:s', $last_seen) : '1970-01-01 00:00:00';

$out = [
    'server_time'        => time(),
    'new_orders'         => 0,
    'pending_payments'   => 0,
    'pending_designs'    => 0,
    'pending_custom'     => 0,
    'unread_chats'       => 0,
    'unread_contacts'    => 0,
    'low_stock'          => 0,
];

// New orders since last seen
if ($stmt = $conn->prepare("SELECT COUNT(*) AS c FROM orders WHERE created_at > ?")) {
    $stmt->bind_param("s", $since_dt);
    $stmt->execute();
    $out['new_orders'] = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
}

// Pending payment verifications (from gcash/maya)
$pp = $conn->query("SHOW COLUMNS FROM orders LIKE 'payment_status'");
if ($pp && $pp->num_rows > 0) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE payment_status = 'pending_verification'");
    if ($r) $out['pending_payments'] = (int)$r->fetch_assoc()['c'];
}

// Pending custom designs
$cd = $conn->query("SHOW TABLES LIKE 'custom_designs'");
if ($cd && $cd->num_rows > 0) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM custom_designs WHERE status = 'pending'");
    if ($r) $out['pending_designs'] = (int)$r->fetch_assoc()['c'];
}

// Pending custom orders
$co = $conn->query("SHOW TABLES LIKE 'custom_orders'");
if ($co && $co->num_rows > 0) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM custom_orders WHERE status IN ('pending_payment','payment_uploaded')");
    if ($r) $out['pending_custom'] = (int)$r->fetch_assoc()['c'];
}

// Unread support chat messages (user-sent only)
$sc = $conn->query("SHOW TABLES LIKE 'support_messages'");
if ($sc && $sc->num_rows > 0) {
    $cols = $conn->query("SHOW COLUMNS FROM support_messages LIKE 'is_read'");
    if ($cols && $cols->num_rows > 0) {
        $r = $conn->query("SELECT COUNT(*) AS c FROM support_messages WHERE is_read = 0 AND sender_type = 'user'");
        if ($r) $out['unread_chats'] = (int)$r->fetch_assoc()['c'];
    }
}

// Unread contact messages
$ct = $conn->query("SHOW TABLES LIKE 'contact_messages'");
if ($ct && $ct->num_rows > 0) {
    $cols = $conn->query("SHOW COLUMNS FROM contact_messages LIKE 'status'");
    if ($cols && $cols->num_rows > 0) {
        $r = $conn->query("SELECT COUNT(*) AS c FROM contact_messages WHERE status = 'new' OR status = 'unread' OR status IS NULL");
        if ($r) $out['unread_contacts'] = (int)$r->fetch_assoc()['c'];
    }
}

// Low stock items
if (productsHasStockColumn()) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM products WHERE status = 'active' AND stock <= 5");
    if ($r) $out['low_stock'] = (int)$r->fetch_assoc()['c'];
}

$out['total_attention'] = $out['new_orders'] + $out['pending_payments'] + $out['pending_designs']
                       + $out['pending_custom'] + $out['unread_chats'] + $out['unread_contacts'];

echo json_encode($out);
