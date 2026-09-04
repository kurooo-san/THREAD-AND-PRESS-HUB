<?php
require 'includes/config.php';
redirectToLogin();

$pageTitle = 'Order Tracking';

$orderId = intval($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    header("Location: custom-design.php");
    exit();
}

// Get order details
$stmt = $conn->prepare("SELECT co.*, cd.design_image as design_preview, cd.design_data, cd.notes as design_notes FROM custom_orders co LEFT JOIN custom_designs cd ON co.design_id = cd.id WHERE co.id = ? AND co.user_id = ?");
$stmt->bind_param("ii", $orderId, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: custom-design.php");
    exit();
}

// Get payment info
$payStmt = $conn->prepare("SELECT * FROM custom_order_payments WHERE custom_order_id = ? ORDER BY created_at DESC LIMIT 1");
$payStmt->bind_param("i", $orderId);
$payStmt->execute();
$payment = $payStmt->get_result()->fetch_assoc();
$payStmt->close();

$typeNames = ['tshirt' => 'T-Shirt', 'hoodie' => 'Hoodie', 'polo' => 'Polo'];
$typeName = $typeNames[$order['product_type']] ?? 'T-Shirt';

// Status configuration
$statusConfig = [
    'pending_payment'    => ['label' => 'Pending Payment',    'icon' => 'fas fa-clock',         'color' => '#e67e22', 'step' => 0],
    'payment_uploaded'   => ['label' => 'Payment Uploaded',   'icon' => 'fas fa-upload',        'color' => '#3498db', 'step' => 1],
    'payment_verified'   => ['label' => 'Payment Verified',   'icon' => 'fas fa-check-circle',  'color' => '#27ae60', 'step' => 2],
    'processing'         => ['label' => 'Processing',         'icon' => 'fas fa-cog',           'color' => '#8e44ad', 'step' => 3],
    'printing'           => ['label' => 'Printing',           'icon' => 'fas fa-print',         'color' => '#2980b9', 'step' => 4],
    'ready_pickup'       => ['label' => 'Ready for Pickup',   'icon' => 'fas fa-box',           'color' => '#16a085', 'step' => 5],
    'delivered'          => ['label' => 'Delivered',           'icon' => 'fas fa-check-double',  'color' => '#27ae60', 'step' => 6],
    'cancelled'          => ['label' => 'Cancelled',          'icon' => 'fas fa-times-circle',  'color' => '#e74c3c', 'step' => -1],
];

$currentStatus = $statusConfig[$order['status']] ?? $statusConfig['pending_payment'];
$currentStep = $currentStatus['step'];

// One-shot success animation — only right after paying, never on a later visit
// to this tracking page.
require_once 'includes/payment-success.php';
$successFlash = takePaymentSuccessFlash($orderId);
?>

<?php include 'includes/header/header.php'; ?>

<?php
if ($successFlash !== null) {
    $isCod = ($successFlash['kind'] ?? '') === 'custom_cod';
    renderPaymentSuccess([
        'title'   => $isCod ? 'Order Placed!' : 'Payment Submitted!',
        'message' => $isCod
            ? 'Cash on Delivery — please prepare the exact amount when you receive your custom apparel.'
            : 'We received your proof of payment. We will verify it shortly and start printing your design.',
        'badge'   => 'Custom Order #' . (int) $orderId,
        'amount'  => $successFlash['total'] ?? null,
    ]);
}
?>

<style>
.tracking-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 1rem;
}
.tracking-header {
    text-align: center;
    margin-bottom: 2rem;
}
.tracking-header h1 {
    font-size: 2rem;
    font-weight: 800;
}
.tracking-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid var(--border-light, #e5e5e5);
    box-shadow: 0 2px 16px rgba(0,0,0,0.06);
    overflow: hidden;
    margin-bottom: 1.5rem;
}
.tracking-card-header {
    padding: 1rem 1.5rem;
    background: #fafafa;
    border-bottom: 1px solid #eee;
    font-weight: 700;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.tracking-card-body {
    padding: 1.5rem;
}
.order-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.9rem;
    border-radius: 20px;
    font-size: 0.82rem;
    font-weight: 700;
    color: #fff;
}
/* Timeline */
.order-timeline {
    position: relative;
    padding: 1rem 0;
}
.timeline-item {
    display: flex;
    gap: 1rem;
    margin-bottom: 0;
    position: relative;
    padding-bottom: 2rem;
}
.timeline-item:last-child {
    padding-bottom: 0;
}
.timeline-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
    z-index: 1;
    background: #eee;
    color: #999;
    position: relative;
}
.timeline-item.completed .timeline-icon {
    background: #27ae60;
    color: #fff;
}
.timeline-item.current .timeline-icon {
    background: var(--accent-green, #2d6a4f);
    color: #fff;
    box-shadow: 0 0 0 4px rgba(45,106,79,0.2);
    animation: pulse-ring 2s infinite;
}
.timeline-item.cancelled .timeline-icon {
    background: #e74c3c;
    color: #fff;
}
@keyframes pulse-ring {
    0% { box-shadow: 0 0 0 0 rgba(45,106,79,0.4); }
    70% { box-shadow: 0 0 0 8px rgba(45,106,79,0); }
    100% { box-shadow: 0 0 0 0 rgba(45,106,79,0); }
}
.timeline-line {
    position: absolute;
    left: 19px;
    top: 40px;
    bottom: 0;
    width: 2px;
    background: #eee;
}
.timeline-item.completed .timeline-line {
    background: #27ae60;
}
.timeline-content {
    flex: 1;
    padding-top: 0.5rem;
}
.timeline-content h6 {
    font-weight: 700;
    font-size: 0.9rem;
    margin-bottom: 0.2rem;
    color: #333;
}
.timeline-item:not(.completed):not(.current) .timeline-content h6 {
    color: #bbb;
}
.timeline-content p {
    font-size: 0.78rem;
    color: #999;
    margin: 0;
}
.timeline-item.completed .timeline-content p,
.timeline-item.current .timeline-content p {
    color: #666;
}
/* Order details grid */
.order-detail-grid {
    display: grid;
    grid-template-columns: 160px 1fr;
    gap: 1.5rem;
    align-items: start;
}
.order-detail-img {
    width: 100%;
    border-radius: 12px;
    border: 1px solid #eee;
}
.order-info-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.order-info-list li {
    display: flex;
    justify-content: space-between;
    padding: 0.45rem 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.88rem;
}
.order-info-list li:last-child {
    border-bottom: none;
}
.order-info-list .label { color: #888; }
.order-info-list .value { font-weight: 600; color: #333; }
.payment-info-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.3rem 0.7rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
}
.payment-info-badge.pending { background: #fff3cd; color: #856404; }
.payment-info-badge.verified { background: #d4edda; color: #155724; }
.payment-info-badge.rejected { background: #f8d7da; color: #721c24; }
.btn-action-row {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}
.btn-action-row a {
    flex: 1;
    min-width: 150px;
    text-align: center;
    padding: 0.75rem 1rem;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.2s;
}
.btn-track-primary {
    background: var(--accent-green, #2d6a4f);
    color: #fff;
}
.btn-track-primary:hover {
    background: #245a42;
    color: #fff;
}
.btn-track-outline {
    background: #fff;
    border: 1px solid #ddd;
    color: #666;
}
.btn-track-outline:hover {
    border-color: #aaa;
    color: #333;
}
.color-swatch { display:inline-block; width:16px; height:16px; border-radius:50%; border:2px solid #ddd; vertical-align:middle; margin-right:0.3rem; }

/* Progress Steps */
.order-steps { display:flex; justify-content:center; gap:0; margin-bottom:2rem; }
.order-step { display:flex; align-items:center; gap:0.5rem; font-size:0.82rem; color:#bbb; font-weight:600; }
.order-step.active { color:var(--accent-green,#2d6a4f); }
.order-step.completed { color:#27ae60; }
.order-step .step-num { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:700; background:#eee; color:#999; }
.order-step.active .step-num { background:var(--accent-green,#2d6a4f); color:#fff; }
.order-step.completed .step-num { background:#27ae60; color:#fff; }
.step-connector { width:40px; height:2px; background:#eee; margin:0 0.25rem; }
.step-connector.completed { background:#27ae60; }

@media (max-width:768px) { 
    .order-detail-grid { grid-template-columns: 1fr; }
    .order-detail-img { max-width: 200px; margin: 0 auto; }
    .order-steps { flex-wrap: wrap; gap: 0.5rem; }
    .step-connector { display: none; }
}
</style>

<div class="tracking-container">
    <!-- Progress Steps -->
    <div class="order-steps">
        <div class="order-step completed"><span class="step-num"><i class="fas fa-check"></i></span> Design</div>
        <div class="step-connector completed"></div>
        <div class="order-step completed"><span class="step-num"><i class="fas fa-check"></i></span> Summary</div>
        <div class="step-connector completed"></div>
        <div class="order-step completed"><span class="step-num"><i class="fas fa-check"></i></span> Payment</div>
        <div class="step-connector completed"></div>
        <div class="order-step active"><span class="step-num">4</span> Tracking</div>
    </div>

    <div class="tracking-header">
        <h1><i class="fas fa-truck me-2"></i>Order Tracking</h1>
        <p class="text-muted">Track your custom apparel order #<?php echo $orderId; ?></p>
    </div>

    <!-- Current Status -->
    <div class="tracking-card">
        <div class="tracking-card-header">
            <span><i class="fas fa-info-circle me-2"></i>Order #<?php echo $orderId; ?></span>
            <span class="order-status-badge" style="background:<?php echo $currentStatus['color']; ?>">
                <i class="<?php echo $currentStatus['icon']; ?>"></i>
                <?php echo $currentStatus['label']; ?>
            </span>
        </div>
        <div class="tracking-card-body">
            <div class="order-detail-grid">
                <div>
                    <img src="<?php echo htmlspecialchars($order['design_image']); ?>" class="order-detail-img" alt="Design" onerror="this.src='https://placehold.co/200x250/f0f0f0/999?text=Design'">
                </div>
                <div>
                    <ul class="order-info-list">
                        <li>
                            <span class="label">Product</span>
                            <span class="value">Custom <?php echo htmlspecialchars($typeName); ?></span>
                        </li>
                        <li>
                            <span class="label">Color</span>
                            <span class="value"><span class="color-swatch" style="background:<?php echo htmlspecialchars($order['apparel_color']); ?>"></span><?php echo htmlspecialchars($order['apparel_color']); ?></span>
                        </li>
                        <li>
                            <span class="label">Size</span>
                            <span class="value"><?php echo htmlspecialchars($order['size']); ?></span>
                        </li>
                        <li>
                            <span class="label">Quantity</span>
                            <span class="value"><?php echo (int)$order['quantity']; ?></span>
                        </li>
                        <li>
                            <span class="label">Total</span>
                            <span class="value" style="color:var(--accent-green,#2d6a4f); font-size:1.1rem;">₱<?php echo number_format($order['total_price'], 2); ?></span>
                        </li>
                        <?php if ($payment): ?>
                        <li>
                            <span class="label">Payment Method</span>
                            <span class="value"><?php echo strtoupper(htmlspecialchars($payment['payment_method'])); ?></span>
                        </li>
                        <li>
                            <span class="label">Payment Status</span>
                            <span class="payment-info-badge <?php echo htmlspecialchars($payment['payment_status']); ?>">
                                <?php echo htmlspecialchars($payment['payment_status']); ?>
                            </span>
                        </li>
                        <?php endif; ?>
                        <li>
                            <span class="label">Order Date</span>
                            <span class="value"><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></span>
                        </li>
                        <?php if ($order['admin_notes']): ?>
                        <li>
                            <span class="label">Admin Note</span>
                            <span class="value" style="color:#2d6a4f;"><?php echo htmlspecialchars($order['admin_notes']); ?></span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Timeline -->
    <div class="tracking-card">
        <div class="tracking-card-header">
            <span><i class="fas fa-stream me-2"></i>Order Timeline</span>
        </div>
        <div class="tracking-card-body">
            <?php if ($order['status'] === 'cancelled'): ?>
                <div class="order-timeline">
                    <div class="timeline-item cancelled">
                        <div class="timeline-icon"><i class="fas fa-times"></i></div>
                        <div class="timeline-content">
                            <h6>Order Cancelled</h6>
                            <p>This order has been cancelled.</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="order-timeline">
                    <?php
                    $steps = [
                        ['step' => 0, 'icon' => 'fas fa-clock',        'title' => 'Pending Payment',    'desc' => 'Order created, awaiting payment.'],
                        ['step' => 1, 'icon' => 'fas fa-upload',       'title' => 'Payment Uploaded',   'desc' => 'Payment proof submitted, awaiting admin verification.'],
                        ['step' => 2, 'icon' => 'fas fa-check-circle', 'title' => 'Payment Verified',   'desc' => 'Payment has been verified by admin.'],
                        ['step' => 3, 'icon' => 'fas fa-cog',          'title' => 'Processing',         'desc' => 'Your order is being processed and prepared for printing.'],
                        ['step' => 4, 'icon' => 'fas fa-print',        'title' => 'Printing',           'desc' => 'Your custom design is being printed on your apparel.'],
                        ['step' => 5, 'icon' => 'fas fa-box',          'title' => 'Ready for Pickup',   'desc' => 'Your order is ready! Come pick it up or wait for delivery.'],
                        ['step' => 6, 'icon' => 'fas fa-check-double', 'title' => 'Delivered',          'desc' => 'Order has been delivered. Enjoy your custom apparel!'],
                    ];
                    foreach ($steps as $s): 
                        $stepClass = '';
                        if ($s['step'] < $currentStep) $stepClass = 'completed';
                        elseif ($s['step'] == $currentStep) $stepClass = 'current';
                    ?>
                    <div class="timeline-item <?php echo $stepClass; ?>">
                        <div class="timeline-icon"><i class="<?php echo $s['icon']; ?>"></i></div>
                        <?php if ($s !== end($steps)): ?>
                        <div class="timeline-line"></div>
                        <?php endif; ?>
                        <div class="timeline-content">
                            <h6><?php echo $s['title']; ?></h6>
                            <p><?php echo $s['desc']; ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Actions -->
    <div class="btn-action-row">
        <a href="custom-design.php" class="btn-track-outline">
            <i class="fas fa-palette me-2"></i>Create New Design
        </a>
        <a href="my-custom-orders.php" class="btn-track-primary">
            <i class="fas fa-list me-2"></i>All Custom Orders
        </a>
        <?php if ($order['status'] !== 'pending_payment' && $order['status'] !== 'cancelled'): ?>
        <a href="custom-invoice.php?order_id=<?php echo (int)$order['id']; ?>" class="btn-track-primary" style="background:#1a1a1a;" target="_blank">
            <i class="fas fa-file-invoice me-2"></i>Download Invoice
        </a>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer/footer.php'; ?>
