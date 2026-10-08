<?php
// Tiny poll target for js/live-status.js: a version stamp that changes whenever
// the order (or its latest payment) is updated. The page itself is only
// re-fetched when this changes, so polling stays cheap.
require 'includes/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['v' => null]);
    exit();
}

$id     = (int) ($_GET['id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$admin  = ($_SESSION['user_type'] ?? '') === 'admin';

if (($_GET['type'] ?? '') === 'custom') {
    // Same ownership rule as custom-order-tracking.php.
    $stmt = $conn->prepare("SELECT CONCAT(co.status, '|', co.updated_at, '|',
                                   COALESCE((SELECT MAX(p.updated_at) FROM custom_order_payments p WHERE p.custom_order_id = co.id), ''))
                              FROM custom_orders co WHERE co.id = ? AND co.user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
} else {
    // Same ownership rule as order_details.php (owner, or any admin).
    $stmt = $conn->prepare("SELECT CONCAT(status, '|', updated_at) FROM orders WHERE id = ? AND (user_id = ? OR ? = 1)");
    $isAdmin = $admin ? 1 : 0;
    $stmt->bind_param("iii", $id, $userId, $isAdmin);
}
$stmt->execute();
$row = $stmt->get_result()->fetch_row();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo json_encode(['v' => null]);
    exit();
}
echo json_encode(['v' => md5($row[0])]);
