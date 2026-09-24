<?php
// invoice.php — Generate downloadable PDF invoice for an order
require_once 'includes/config.php';
require_once 'vendor/autoload.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$order_id = (int)($_GET['order_id'] ?? 0);
$is_admin = ($_SESSION['user_type'] ?? '') === 'admin';

if ($order_id <= 0) {
    http_response_code(400);
    exit('Invalid order ID.');
}

// Fetch order — restrict to owner unless admin
if ($is_admin) {
    $stmt = $conn->prepare("SELECT o.*, u.fullname, u.email, u.phone FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->bind_param("i", $order_id);
} else {
    $stmt = $conn->prepare("SELECT o.*, u.fullname, u.email, u.phone FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ? AND o.user_id = ?");
    $stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
}
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

// Fetch items
$items_stmt = $conn->prepare("SELECT oi.*, p.name AS product_name FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

// Build HTML
$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Invoice #' . $order_id . '</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; font-size: 12px; }
    .header { display: flex; justify-content: space-between; border-bottom: 3px solid #1a1a1a; padding-bottom: 15px; margin-bottom: 25px; }
    .header h1 { margin: 0; font-size: 28px; letter-spacing: 2px; }
    .header .meta { text-align: right; font-size: 11px; }
    .meta strong { font-size: 14px; }
    .section { margin-bottom: 18px; }
    .section h3 { background: #1a1a1a; color: #fff; padding: 6px 10px; margin: 0 0 8px; font-size: 13px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 8px 10px; border-bottom: 1px solid #e5e5e5; text-align: left; }
    th { background: #f5f5f5; font-size: 11px; text-transform: uppercase; }
    .text-right { text-align: right; }
    .totals { width: 50%; margin-left: 50%; margin-top: 15px; }
    .totals td { border: none; padding: 4px 10px; }
    .totals .grand { border-top: 2px solid #1a1a1a; font-weight: bold; font-size: 14px; }
    .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #666; border-top: 1px solid #ddd; padding-top: 12px; }
    .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 10px; text-transform: uppercase; }
    .badge-paid { background: #10b981; color: #fff; }
    .badge-pending { background: #f59e0b; color: #fff; }
</style>
</head><body>';

$html .= '<div class="header">
    <div>
        <h1>THREAD &amp; PRESS HUB</h1>
        <div style="font-size:11px;">Premium Apparel &amp; Custom Designs</div>
    </div>
    <div class="meta">
        <div><strong>INVOICE #' . str_pad($order_id, 6, '0', STR_PAD_LEFT) . '</strong></div>
        <div>Date: ' . date('M d, Y', strtotime($order['created_at'])) . '</div>
        <div>Status: ' . ucfirst(htmlspecialchars($order['status'])) . '</div>
    </div>
</div>';

$html .= '<div class="section"><h3>Bill To</h3>
    <div><strong>' . htmlspecialchars($order['fullname']) . '</strong></div>
    <div>' . htmlspecialchars($order['email']) . '</div>
    <div>' . htmlspecialchars($order['phone'] ?? '') . '</div>
    <div style="margin-top:6px;">' . nl2br(htmlspecialchars($order['delivery_address'] ?? '')) . '</div>
</div>';

$html .= '<div class="section"><h3>Order Items</h3><table>
    <thead><tr><th>#</th><th>Item</th><th>Color / Size</th><th class="text-right">Qty</th><th class="text-right">Unit</th><th class="text-right">Subtotal</th></tr></thead>
    <tbody>';
$i = 1;
foreach ($items as $it) {
    $unit = (float)($it['unit_price'] ?? $it['price'] ?? 0);
    $line = $unit * (int)$it['quantity'];
    $variant = trim(($it['color'] ?? '') . ($it['size'] ? ' / ' . $it['size'] : ''));
    $html .= '<tr>
        <td>' . $i++ . '</td>
        <td>' . htmlspecialchars($it['product_name'] ?? 'Product #' . $it['product_id']) . '</td>
        <td>' . htmlspecialchars($variant ?: '-') . '</td>
        <td class="text-right">' . (int)$it['quantity'] . '</td>
        <td class="text-right">PHP ' . number_format($unit, 2) . '</td>
        <td class="text-right">PHP ' . number_format($line, 2) . '</td>
    </tr>';
}
$html .= '</tbody></table></div>';

$html .= '<table class="totals">
    <tr><td>Subtotal:</td><td class="text-right">PHP ' . number_format((float)$order['subtotal'], 2) . '</td></tr>';
if ((float)$order['discount_amount'] > 0) {
    $html .= '<tr><td>Discount (' . htmlspecialchars($order['discount_type']) . '):</td><td class="text-right">- PHP ' . number_format((float)$order['discount_amount'], 2) . '</td></tr>';
}
if (isset($order['coupon_discount']) && (float)$order['coupon_discount'] > 0) {
    $html .= '<tr><td>Coupon (' . htmlspecialchars($order['coupon_code'] ?? '') . '):</td><td class="text-right">- PHP ' . number_format((float)$order['coupon_discount'], 2) . '</td></tr>';
}
// VAT as the reconciling difference — the printed breakdown always sums to the
// stored total; orders placed before VAT existed simply show no VAT line.
$vatShown = (float)$order['total']
    - ((float)$order['subtotal'] - (float)$order['discount_amount'] - (float)($order['coupon_discount'] ?? 0))
    - (float)$order['delivery_fee'];
if ($vatShown > 0.009) {
    $html .= '<tr><td>VAT (12%):</td><td class="text-right">PHP ' . number_format($vatShown, 2) . '</td></tr>';
}
$html .= '<tr><td>Delivery Fee:</td><td class="text-right">PHP ' . number_format((float)$order['delivery_fee'], 2) . '</td></tr>
    <tr class="grand"><td>TOTAL:</td><td class="text-right">PHP ' . number_format((float)$order['total'], 2) . '</td></tr>
</table>';

$pay_status = $order['payment_status'] ?? '';
$badge = ($pay_status === 'verified') ? '<span class="badge badge-paid">PAID</span>' : '<span class="badge badge-pending">' . strtoupper(htmlspecialchars($pay_status ?: 'unpaid')) . '</span>';
$html .= '<div class="section" style="margin-top:25px;"><h3>Payment</h3>
    <div>Method: <strong>' . htmlspecialchars(paymentMethodLabel($order['payment_method'])) . '</strong> ' . $badge . '</div>';
if (!empty($order['payment_reference'])) {
    $html .= '<div>Reference: ' . htmlspecialchars($order['payment_reference']) . '</div>';
}
$html .= '</div>';

$html .= '<div class="footer">
    Thank you for shopping with Thread &amp; Press Hub!<br>
    For inquiries, contact us at support@threadandpresshub.com
</div></body></html>';

// Render PDF
$dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'invoice-' . str_pad($order_id, 6, '0', STR_PAD_LEFT) . '.pdf';
if (ob_get_length()) { ob_end_clean(); }
$dompdf->stream($filename, ['Attachment' => true]);
exit();
