<?php
// custom-invoice.php — Generate downloadable PDF invoice for a custom order
require_once 'includes/config.php';
require_once 'vendor/autoload.php';
require_once 'includes/invoice-image.php';
require_once 'includes/apparel-config.php';

redirectToLogin();

$order_id = (int)($_GET['order_id'] ?? 0);
$is_admin = ($_SESSION['user_type'] ?? '') === 'admin';

if ($order_id <= 0) {
    http_response_code(400);
    exit('Invalid order ID.');
}

// Fetch custom order — restrict to owner unless admin
if ($is_admin) {
    $stmt = $conn->prepare("SELECT co.*, u.fullname, u.email, u.phone FROM custom_orders co JOIN users u ON co.user_id = u.id WHERE co.id = ?");
    $stmt->bind_param("i", $order_id);
} else {
    $stmt = $conn->prepare("SELECT co.*, u.fullname, u.email, u.phone FROM custom_orders co JOIN users u ON co.user_id = u.id WHERE co.id = ? AND co.user_id = ?");
    $stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
}
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    exit('Custom order not found.');
}

// Billing address. `users` has no single `address` column — it stores the
// parts separately — so assemble it the same way checkout.php does. The old
// lookup asked for a column that does not exist, so this was always blank.
$address = '';
$a = $conn->prepare("SELECT street_address, barangay, city, province, zipcode FROM users WHERE id = ?");
$a->bind_param("i", $order['user_id']);
$a->execute();
if ($u = $a->get_result()->fetch_assoc()) {
    $address = implode(', ', array_filter([
        $u['street_address'] ?? '',
        $u['barangay'] ?? '',
        $u['city'] ?? '',
        $u['province'] ?? '',
        $u['zipcode'] ?? ''
    ]));
}
$a->close();

// Fetch latest payment record
$payStmt = $conn->prepare("SELECT * FROM custom_order_payments WHERE custom_order_id = ? ORDER BY created_at DESC LIMIT 1");
$payStmt->bind_param("i", $order_id);
$payStmt->execute();
$payment = $payStmt->get_result()->fetch_assoc();
$payStmt->close();

$designBack = '';
if (!empty($order['design_id'])) {
    // SELECT *: design_image_back is added by the Design Studio, older databases may not have it.
    $d = $conn->prepare("SELECT * FROM custom_designs WHERE id = ?");
    $d->bind_param("i", $order['design_id']);
    $d->execute();
    $designBack = ($d->get_result()->fetch_assoc() ?: [])['design_image_back'] ?? '';
    $d->close();
}

$typeName = getApparelConfig()[$order['product_type']]['label'] ?? 'Custom Apparel';
$qty = (int)$order['quantity'];
$basePrice = (float)$order['base_price'];
$printCost = (float)$order['print_cost'];
$colorCost = (float)$order['color_cost'];
$unitPrice = $basePrice + $printCost + $colorCost;

// Build HTML
$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Custom Invoice #' . $order_id . '</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; font-size: 12px; }
    .header { border-collapse: separate; border-spacing: 0; border-bottom: 3px solid #1a1a1a; margin-bottom: 25px; }
    .header td { border: none; padding: 0 0 15px; vertical-align: top; }
    .header h1 { margin: 0; font-size: 24px; letter-spacing: 1px; }
    .header .meta { text-align: right; font-size: 11px; white-space: nowrap; }
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
    .design-meta td { padding: 4px 10px; border: none; }
    .design-meta td:first-child { width: 140px; color: #666; }
</style>
</head><body>';

$html .= '<table class="header"><tr>
    ' . (($logo = invoiceImage(brandLogoUrl(), 64, 64)) !== '' ? '<td style="width:76px;">' . $logo . '</td>' : '') . '
    <td>
        <h1>' . htmlspecialchars(mb_strtoupper(brand('name')), ENT_QUOTES) . '</h1>
        ' . (brand('invoice_tagline') !== '' ? '<div style="font-size:11px;">' . brandHtml('invoice_tagline') . '</div>' : '') . '
    </td>
    <td class="meta">
        <div><strong>CUSTOM INVOICE #C' . str_pad($order_id, 6, '0', STR_PAD_LEFT) . '</strong></div>
        <div>Date: ' . date('M d, Y', strtotime($order['created_at'])) . '</div>
        <div>Status: ' . htmlspecialchars(['ready_pickup' => 'Ready for pickup', 'delivered' => 'Picked up'][$order['status']] ?? ucfirst(str_replace('_', ' ', $order['status']))) . '</div>
    </td>
</tr></table>';

$html .= '<div class="section"><h3>Bill To</h3>
    <div><strong>' . htmlspecialchars($order['fullname']) . '</strong></div>
    <div>' . htmlspecialchars($order['email']) . '</div>
    <div>' . htmlspecialchars($order['phone'] ?? '') . '</div>';
if ($address !== '') {
    $html .= '<div style="margin-top:6px;">' . nl2br(htmlspecialchars($address)) . '</div>';
}
$html .= '</div>';

// The garment mockups. The back comes from the saved design, the same as on the order summary.
$pictures = '';
foreach (['Front' => $order['design_image'] ?? '', 'Back' => $designBack] as $side => $src) {
    $img = invoiceImage((string) $src, 130, 165);
    if ($img !== '') {
        $pictures .= '<td style="border:none;padding:0 0 0 8px;text-align:center;font-size:10px;color:#666;">' . $img . '<br>' . $side . '</td>';
    }
}

// The colour is stored as a hex code, so show it as well as name it.
$swatch = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) $order['apparel_color'])
    ? '<span style="display:inline-block;width:10px;height:10px;border:1px solid #999;background:' . $order['apparel_color'] . ';"></span> '
    : '';

$html .= '<div class="section"><h3>Custom Design Details</h3>
    <table><tr><td style="border:none;padding:0;vertical-align:top;">
    <table class="design-meta">
        <tr><td>Apparel Type:</td><td><strong>' . htmlspecialchars($typeName) . '</strong></td></tr>
        <tr><td>Color:</td><td>' . $swatch . htmlspecialchars($order['apparel_color']) . '</td></tr>
        <tr><td>Size:</td><td>' . htmlspecialchars($order['size']) . '</td></tr>
        <tr><td>Quantity:</td><td>' . $qty . '</td></tr>';
if (!empty($order['notes'])) {
    $html .= '<tr><td>Notes:</td><td>' . nl2br(htmlspecialchars($order['notes'])) . '</td></tr>';
}
$html .= '</table>
    </td>' . ($pictures !== '' ? '<td style="border:none;padding:0;width:290px;vertical-align:top;"><table style="width:auto;margin-left:auto;"><tr>' . $pictures . '</tr></table></td>' : '') . '</tr></table>
</div>';

$html .= '<div class="section"><h3>Cost Breakdown</h3><table>
    <thead><tr><th>Item</th><th class="text-right">Unit Price</th><th class="text-right">Qty</th><th class="text-right">Subtotal</th></tr></thead>
    <tbody>
        <tr>
            <td>' . htmlspecialchars($typeName) . ' Base</td>
            <td class="text-right">PHP ' . number_format($basePrice, 2) . '</td>
            <td class="text-right">' . $qty . '</td>
            <td class="text-right">PHP ' . number_format($basePrice * $qty, 2) . '</td>
        </tr>';
if ($printCost > 0) {
    $html .= '<tr>
        <td>Print Cost</td>
        <td class="text-right">PHP ' . number_format($printCost, 2) . '</td>
        <td class="text-right">' . $qty . '</td>
        <td class="text-right">PHP ' . number_format($printCost * $qty, 2) . '</td>
    </tr>';
}
if ($colorCost > 0) {
    $html .= '<tr>
        <td>Color Cost</td>
        <td class="text-right">PHP ' . number_format($colorCost, 2) . '</td>
        <td class="text-right">' . $qty . '</td>
        <td class="text-right">PHP ' . number_format($colorCost * $qty, 2) . '</td>
    </tr>';
}
$html .= '</tbody></table></div>';

$html .= '<table class="totals">
    <tr><td>Subtotal:</td><td class="text-right">PHP ' . number_format((float)$order['subtotal'], 2) . '</td></tr>';
if ((float)$order['discount_amount'] > 0) {
    $html .= '<tr><td>Discount (' . htmlspecialchars(['pwd' => 'PWD', 'senior' => 'Senior Citizen'][$order['discount_type']] ?? ucfirst((string) $order['discount_type'])) . '):</td><td class="text-right">- PHP ' . number_format((float)$order['discount_amount'], 2) . '</td></tr>';
}
$html .= '<tr class="grand"><td>TOTAL:</td><td class="text-right">PHP ' . number_format((float)$order['total_price'], 2) . '</td></tr>
</table>';

if ($payment) {
    $pay_status = $payment['payment_status'] ?? '';
    $badge = ($pay_status === 'verified')
        ? '<span class="badge badge-paid">PAID</span>'
        : '<span class="badge badge-pending">' . strtoupper(htmlspecialchars($pay_status ?: 'unpaid')) . '</span>';
    $html .= '<div class="section" style="margin-top:25px;"><h3>Payment</h3>
        <div>Method: <strong>' . htmlspecialchars(paymentMethodLabel($payment['payment_method'], true)) . '</strong> ' . $badge . '</div>';
    if (!empty($payment['reference_number'])) {
        $html .= '<div>Reference: ' . htmlspecialchars($payment['reference_number']) . '</div>';
    }
    $html .= '<div>Amount: PHP ' . number_format((float)$payment['amount'], 2) . '</div>';
    $html .= '</div>';
}

$html .= '<div class="footer">
    Thank you for choosing ' . brandHtml('name') . ' for your custom design!<br>
    For inquiries, contact us at ' . SUPPORT_EMAIL . '
</div></body></html>';

// Render PDF
$dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'custom-invoice-C' . str_pad($order_id, 6, '0', STR_PAD_LEFT) . '.pdf';
if (ob_get_length()) { ob_end_clean(); }
$dompdf->stream($filename, ['Attachment' => true]);
exit();
