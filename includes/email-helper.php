<?php
/**
 * Email Notification System for Thread & Press Hub
 * Supports PHP mail() and PHPMailer when available.
 * 
 * Configure SMTP in .env:
 *   SMTP_HOST=smtp.gmail.com
 *   SMTP_PORT=587
 *   SMTP_USER=your@email.com
 *   SMTP_PASS=your_app_password
 *   MAIL_FROM=noreply@threadandpress.com
 *   MAIL_FROM_NAME=Thread & Press Hub
 */

if (!defined('MAIL_FROM')) define('MAIL_FROM', getenv('MAIL_FROM') ?: 'noreply@threadandpress.com');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Thread & Press Hub');
if (!defined('SMTP_HOST')) define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
if (!defined('SMTP_PORT')) define('SMTP_PORT', getenv('SMTP_PORT') ?: '587');
if (!defined('SMTP_USER')) define('SMTP_USER', getenv('SMTP_USER') ?: '');
if (!defined('SMTP_PASS')) define('SMTP_PASS', getenv('SMTP_PASS') ?: '');

/**
 * Send an email using the best available method
 */
function sendEmail($to, $subject, $htmlBody, $textBody = '') {
    // Try PHPMailer first (if installed via composer)
    $phpmailerPath = __DIR__ . '/../vendor/autoload.php';
    if (!empty(SMTP_HOST) && file_exists($phpmailerPath)) {
        return sendWithPHPMailer($to, $subject, $htmlBody, $textBody);
    }

    // Fallback to PHP mail()
    return sendWithMail($to, $subject, $htmlBody, $textBody);
}

function sendWithMail($to, $subject, $htmlBody, $textBody = '') {
    $boundary = md5(time());
    $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

    $plainText = $textBody ?: strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));

    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= $plainText . "\r\n\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $htmlBody . "\r\n\r\n";
    $body .= "--{$boundary}--";

    $result = @mail($to, $subject, $body, $headers);
    if (!$result) {
        error_log("Email send failed (mail): to={$to}, subject={$subject}");
    }
    return $result;
}

function sendWithPHPMailer($to, $subject, $htmlBody, $textBody = '') {
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = intval(SMTP_PORT);

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody ?: strip_tags($htmlBody);

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email send failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get base email template wrapper
 */
function emailTemplate($title, $content) {
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:20px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
    <tr>
        <td style="background:#1a1a1a;padding:24px 30px;text-align:center;">
            <span style="font-size:24px;font-weight:800;color:#ffffff;letter-spacing:1px;">
                <span style="color:#f0c040;">TP</span> Thread &amp; Press Hub
            </span>
        </td>
    </tr>
    <tr>
        <td style="padding:30px;">
            <h2 style="margin:0 0 20px;color:#1a1a1a;font-size:20px;">' . htmlspecialchars($title) . '</h2>
            ' . $content . '
        </td>
    </tr>
    <tr>
        <td style="background:#f9f9f9;padding:20px 30px;text-align:center;border-top:1px solid #eee;">
            <p style="margin:0;font-size:12px;color:#999;">
                &copy; ' . date('Y') . ' Thread &amp; Press Hub. All rights reserved.<br>
                123 Fashion Ave, Cainta, Rizal, Philippines
            </p>
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>';
}

/**
 * Send order confirmation email
 */
function sendOrderConfirmationEmail($conn, $orderId) {
    $stmt = $conn->prepare("SELECT o.*, u.fullname, u.email FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) return false;

    // Get order items
    $itemStmt = $conn->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $itemStmt->bind_param("i", $orderId);
    $itemStmt->execute();
    $items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $itemStmt->close();

    $itemsHtml = '';
    foreach ($items as $item) {
        $color = !empty($item['color']) ? " ({$item['color']})" : '';
        $size = !empty($item['size']) ? " - {$item['size']}" : '';
        $itemsHtml .= '<tr>
            <td style="padding:8px 0;border-bottom:1px solid #eee;">' . htmlspecialchars($item['name']) . $color . $size . '</td>
            <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:center;">' . $item['quantity'] . '</td>
            <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;">₱' . number_format($item['subtotal'], 2) . '</td>
        </tr>';
    }

    // VAT as the reconciling difference — the emailed breakdown always sums to
    // the stored total; pre-VAT orders simply show no VAT line.
    $vatShown = (float)$order['total']
        - ((float)$order['subtotal'] - (float)$order['discount_amount'] - (float)($order['coupon_discount'] ?? 0))
        - (float)$order['delivery_fee'];

    $content = '
    <p style="color:#555;line-height:1.6;">Hi ' . htmlspecialchars($order['fullname']) . ',</p>
    <p style="color:#555;line-height:1.6;">Thank you for your order! Here are your order details:</p>

    <div style="background:#f9f9f9;border-radius:8px;padding:15px;margin:15px 0;">
        <p style="margin:5px 0;font-size:14px;"><strong>Order #' . $orderId . '</strong></p>
        <p style="margin:5px 0;font-size:13px;color:#666;">Payment: ' . strtoupper($order['payment_method']) . '</p>
        <p style="margin:5px 0;font-size:13px;color:#666;">Delivery: ' . htmlspecialchars($order['delivery_address']) . '</p>
    </div>

    <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin:15px 0;">
        <tr style="background:#f0f0f0;">
            <th style="padding:8px;text-align:left;">Item</th>
            <th style="padding:8px;text-align:center;">Qty</th>
            <th style="padding:8px;text-align:right;">Price</th>
        </tr>
        ' . $itemsHtml . '
    </table>

    <table width="100%" style="font-size:13px;">
        <tr><td style="padding:4px 0;">Subtotal:</td><td style="text-align:right;">₱' . number_format($order['subtotal'], 2) . '</td></tr>'
        . ($order['discount_amount'] > 0 ? '<tr><td style="padding:4px 0;color:#27ae60;">Discount (' . strtoupper($order['discount_type']) . '):</td><td style="text-align:right;color:#27ae60;">-₱' . number_format($order['discount_amount'], 2) . '</td></tr>' : '')
        . ($vatShown > 0.009 ? '<tr><td style="padding:4px 0;">VAT (12%):</td><td style="text-align:right;">₱' . number_format($vatShown, 2) . '</td></tr>' : '')
        . '<tr><td style="padding:4px 0;">Delivery Fee:</td><td style="text-align:right;">₱' . number_format($order['delivery_fee'], 2) . '</td></tr>
        <tr><td style="padding:8px 0;border-top:2px solid #333;font-weight:bold;font-size:15px;">Total:</td>
            <td style="padding:8px 0;border-top:2px solid #333;text-align:right;font-weight:bold;font-size:15px;">₱' . number_format($order['total'], 2) . '</td></tr>
    </table>

    <p style="color:#555;line-height:1.6;margin-top:20px;">We\'ll notify you when your order status changes. You can track your order anytime from your account.</p>';

    $html = emailTemplate('Order Confirmation', $content);
    return sendEmail($order['email'], "Order #{$orderId} Confirmed - Thread & Press Hub", $html);
}

/**
 * Send order status update email
 */
function sendOrderStatusEmail($conn, $orderId, $newStatus) {
    $stmt = $conn->prepare("SELECT o.*, u.fullname, u.email FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) return false;

    $statusLabels = [
        'pending' => ['Pending', '#f39c12', 'Your order is being reviewed.'],
        'confirmed' => ['Confirmed', '#27ae60', 'Your order has been confirmed and is being processed!'],
        'preparing' => ['Preparing', '#3498db', 'Your order is being prepared for shipment.'],
        'out_for_delivery' => ['Out for Delivery', '#9b59b6', 'Your order is on its way to you!'],
        'completed' => ['Completed', '#2ecc71', 'Your order has been delivered. Enjoy your purchase!'],
        'cancelled' => ['Cancelled', '#e74c3c', 'Your order has been cancelled.']
    ];

    $statusInfo = $statusLabels[$newStatus] ?? ['Updated', '#666', 'Your order status has been updated.'];

    $content = '
    <p style="color:#555;line-height:1.6;">Hi ' . htmlspecialchars($order['fullname']) . ',</p>
    <p style="color:#555;line-height:1.6;">Your order status has been updated:</p>

    <div style="text-align:center;margin:20px 0;">
        <div style="display:inline-block;background:' . $statusInfo[1] . ';color:#fff;padding:10px 25px;border-radius:20px;font-weight:bold;font-size:14px;">
            ' . $statusInfo[0] . '
        </div>
    </div>

    <div style="background:#f9f9f9;border-radius:8px;padding:15px;margin:15px 0;">
        <p style="margin:5px 0;font-size:14px;"><strong>Order #' . $orderId . '</strong></p>
        <p style="margin:5px 0;font-size:13px;color:#666;">Total: ₱' . number_format($order['total'], 2) . '</p>
        <p style="margin:5px 0;font-size:13px;color:#555;">' . $statusInfo[2] . '</p>
    </div>';

    $html = emailTemplate('Order Status Update', $content);
    return sendEmail($order['email'], "Order #{$orderId} - " . $statusInfo[0] . " - Thread & Press Hub", $html);
}

/**
 * Get the base URL of the application
 */
function getBaseUrl() {
    $allowedHosts = ['localhost', '127.0.0.1'];
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Strip port for validation
    $hostOnly = strtolower(explode(':', $host)[0]);
    if (!in_array($hostOnly, $allowedHosts)) {
        $host = 'localhost';
    }
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    return $protocol . '://' . $host . $path;
}

/**
 * Send welcome email after registration
 */
function sendWelcomeEmail($email, $fullname) {
    $baseUrl = getBaseUrl();
    $content = '
    <p style="color:#555;line-height:1.6;">Hi ' . htmlspecialchars($fullname) . ',</p>
    <p style="color:#555;line-height:1.6;">Welcome to Thread &amp; Press Hub! Your account has been created successfully.</p>

    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:15px;margin:15px 0;">
        <p style="margin:5px 0;font-size:14px;color:#166534;"><strong>What you can do now:</strong></p>
        <ul style="margin:10px 0;padding-left:20px;color:#555;font-size:13px;line-height:1.8;">
            <li>Browse our latest collections</li>
            <li>Create custom apparel designs</li>
            <li>Track your orders in real-time</li>
            <li>Chat with our AI assistant or support team</li>
        </ul>
    </div>

    <p style="color:#555;line-height:1.6;">Start shopping now and enjoy quality fashion at great prices!</p>
    <p style="text-align:center;margin:20px 0;">
        <a href="' . htmlspecialchars($baseUrl . '/shop.php') . '" 
           style="display:inline-block;background:#1a1a1a;color:#fff;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:bold;">
            Start Shopping
        </a>
    </p>';

    $html = emailTemplate('Welcome to Thread & Press Hub!', $content);
    return sendEmail($email, "Welcome to Thread & Press Hub!", $html);
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $fullname, $resetLink) {
    $content = '
    <p style="color:#555;line-height:1.6;">Hi ' . htmlspecialchars($fullname) . ',</p>
    <p style="color:#555;line-height:1.6;">You requested a password reset for your Thread &amp; Press Hub account.</p>

    <p style="text-align:center;margin:25px 0;">
        <a href="' . htmlspecialchars($resetLink) . '" 
           style="display:inline-block;background:#1a1a1a;color:#fff;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:bold;">
            Reset Password
        </a>
    </p>

    <div style="background:#fef3cd;border:1px solid #ffc107;border-radius:8px;padding:15px;margin:15px 0;">
        <p style="margin:0;font-size:13px;color:#856404;"><strong>Note:</strong> This link will expire in 1 hour. If you did not request this reset, please ignore this email.</p>
    </div>

    <p style="color:#999;font-size:12px;line-height:1.6;margin-top:15px;">If the button above doesn\'t work, copy and paste the following link into your browser:<br>
    <a href="' . htmlspecialchars($resetLink) . '" style="color:#3498db;word-break:break-all;">' . htmlspecialchars($resetLink) . '</a></p>';

    $html = emailTemplate('Password Reset Request', $content);
    return sendEmail($email, "Password Reset - Thread & Press Hub", $html);
}


/**
 * Send custom order confirmation email
 */
/**
 * Send a status-update email for a CUSTOM order (mirrors sendOrderStatusEmail).
 * Called by the admin custom-orders panel whenever the status changes, so the
 * customer gets real-time updates (processing → printing → delivered, etc.).
 */
function sendCustomOrderStatusEmail($conn, $customOrderId, $newStatus) {
    $stmt = $conn->prepare("SELECT co.*, u.fullname, u.email FROM custom_orders co JOIN users u ON co.user_id = u.id WHERE co.id = ?");
    $stmt->bind_param("i", $customOrderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) return false;

    $statusLabels = [
        'pending_payment'  => ['Pending Payment', '#f39c12', 'We are waiting for your payment to start production.'],
        'payment_uploaded' => ['Payment Uploaded', '#3498db', 'We received your payment proof and are verifying it.'],
        'payment_verified' => ['Payment Verified', '#27ae60', 'Your payment is verified! Your custom apparel is queued for production.'],
        'processing'       => ['Processing', '#8e44ad', 'Your custom apparel is now in production.'],
        'printing'         => ['Printing', '#2980b9', 'Your design is being printed right now!'],
        'ready_pickup'     => ['Ready', '#16a085', 'Your custom order is ready! It will be delivered or is ready for pickup.'],
        'delivered'        => ['Delivered', '#2ecc71', 'Your custom order has been delivered. Enjoy wearing your own design!'],
        'cancelled'        => ['Cancelled', '#e74c3c', 'Your custom order has been cancelled. Contact support if you have questions.'],
    ];
    $statusInfo = $statusLabels[$newStatus] ?? ['Updated', '#666', 'Your custom order status has been updated.'];

    $typeNames = ['tshirt' => 'T-Shirt', 'hoodie' => 'Hoodie', 'polo' => 'Polo'];
    $typeName  = $typeNames[$order['product_type']] ?? ucfirst((string)$order['product_type']);
    $trackUrl  = getBaseUrl() . '/custom-order-tracking.php?order_id=' . (int)$customOrderId;

    $content = '
    <p style="color:#555;line-height:1.6;">Hi ' . htmlspecialchars($order['fullname']) . ',</p>
    <p style="color:#555;line-height:1.6;">Your custom order status has been updated:</p>

    <div style="text-align:center;margin:20px 0;">
        <div style="display:inline-block;background:' . $statusInfo[1] . ';color:#fff;padding:10px 25px;border-radius:20px;font-weight:bold;font-size:14px;">
            ' . $statusInfo[0] . '
        </div>
    </div>

    <div style="background:#f9f9f9;border-radius:8px;padding:15px;margin:15px 0;">
        <p style="margin:5px 0;font-size:14px;"><strong>Custom ' . htmlspecialchars($typeName) . ' — Order #' . (int)$customOrderId . '</strong></p>
        <p style="margin:5px 0;font-size:13px;color:#666;">Size: ' . htmlspecialchars((string)$order['size']) . ' · Qty: ' . (int)$order['quantity'] . '</p>
        <p style="margin:5px 0;font-size:13px;color:#666;">Total: ₱' . number_format((float)$order['total_price'], 2) . '</p>
        <p style="margin:5px 0;font-size:13px;color:#555;">' . $statusInfo[2] . '</p>
    </div>

    <div style="text-align:center;margin:20px 0;">
        <a href="' . htmlspecialchars($trackUrl) . '" style="display:inline-block;background:#1a1a1a;color:#fff;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:14px;">Track Your Order</a>
    </div>';

    $html = emailTemplate('Custom Order Update', $content);
    return sendEmail($order['email'], "Custom Order #{$customOrderId} - " . $statusInfo[0] . " - Thread & Press Hub", $html);
}

function sendCustomOrderConfirmationEmail($conn, $customOrderId) {
    $stmt = $conn->prepare("SELECT co.*, u.fullname, u.email, cop.payment_method
        FROM custom_orders co
        JOIN users u ON co.user_id = u.id
        LEFT JOIN custom_order_payments cop ON cop.custom_order_id = co.id
        WHERE co.id = ?
        ORDER BY cop.id DESC LIMIT 1");
    $stmt->bind_param("i", $customOrderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) return false;

    $typeNames = ['tshirt' => 'T-Shirt', 'hoodie' => 'Hoodie', 'polo' => 'Polo'];
    $typeName = $typeNames[$order['product_type']] ?? ucfirst($order['product_type']);
    $paymentLabel = strtoupper($order['payment_method'] ?? 'PENDING');

    $content = '
    <p style="color:#555;line-height:1.6;">Hi ' . htmlspecialchars($order['fullname']) . ',</p>
    <p style="color:#555;line-height:1.6;">Thank you! Your custom apparel order has been received and is being processed.</p>

    <div style="background:#f9f9f9;border-radius:8px;padding:15px;margin:15px 0;">
        <p style="margin:5px 0;font-size:14px;"><strong>Custom Order #' . (int)$customOrderId . '</strong></p>
        <p style="margin:5px 0;font-size:13px;color:#666;">Product: Custom ' . htmlspecialchars($typeName) . '</p>
        <p style="margin:5px 0;font-size:13px;color:#666;">Size: ' . htmlspecialchars($order['size']) . ' &middot; Qty: ' . (int)$order['quantity'] . '</p>
        <p style="margin:5px 0;font-size:13px;color:#666;">Payment Method: ' . htmlspecialchars($paymentLabel) . '</p>
    </div>

    <table width="100%" style="font-size:13px;">
        <tr><td style="padding:4px 0;">Subtotal:</td><td style="text-align:right;">&#8369;' . number_format($order['subtotal'], 2) . '</td></tr>'
        . ($order['discount_amount'] > 0 ? '<tr><td style="padding:4px 0;color:#27ae60;">Discount (' . strtoupper($order['discount_type']) . '):</td><td style="text-align:right;color:#27ae60;">-&#8369;' . number_format($order['discount_amount'], 2) . '</td></tr>' : '')
        . '<tr><td style="padding:8px 0;border-top:2px solid #333;font-weight:bold;font-size:15px;">Total:</td>
            <td style="padding:8px 0;border-top:2px solid #333;text-align:right;font-weight:bold;font-size:15px;">&#8369;' . number_format($order['total_price'], 2) . '</td></tr>
    </table>

    <p style="color:#555;line-height:1.6;margin-top:20px;">We will review your design and payment, then begin printing. You can track the status of your custom order anytime from <em>My Custom Orders</em>.</p>';

    $html = emailTemplate('Custom Order Confirmation', $content);
    return sendEmail($order['email'], "Custom Order #{$customOrderId} Confirmed - Thread & Press Hub", $html);
}