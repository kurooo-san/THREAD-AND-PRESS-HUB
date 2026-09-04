<?php
// AJAX: validate a coupon code against a given subtotal.
// Returns JSON: { ok: bool, message: string, discount: float, code: string }
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'message' => 'Login required.', 'discount' => 0, 'code' => '']);
    exit();
}

$code     = isset($_POST['code'])     ? (string)$_POST['code']     : '';
$subtotal = isset($_POST['subtotal']) ? (float)$_POST['subtotal'] : 0.0;

if ($subtotal <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Cart is empty.', 'discount' => 0, 'code' => '']);
    exit();
}

$res = validateCoupon($code, $subtotal);
echo json_encode([
    'ok'       => $res['ok'],
    'message'  => $res['message'],
    'discount' => round((float)$res['discount'], 2),
    'code'     => $res['ok'] && $res['coupon'] ? $res['coupon']['code'] : '',
]);
