<?php
require 'includes/config.php';
redirectToLogin();

$pageTitle = 'My Custom Orders';

// Run migration if needed
$tableCheck = $conn->query("SHOW TABLES LIKE 'custom_orders'");
if ($tableCheck->num_rows === 0) {
    $migrationSQL = file_get_contents(__DIR__ . '/migrate_custom_orders.sql');
    if ($migrationSQL) {
        $conn->multi_query($migrationSQL);
        while ($conn->next_result()) {;}
    }
}

// Get all custom orders for this user
$stmt = $conn->prepare("
    SELECT co.*, cop.payment_method, cop.payment_status 
    FROM custom_orders co 
    LEFT JOIN custom_order_payments cop ON co.id = cop.custom_order_id 
    WHERE co.user_id = ? 
    ORDER BY co.created_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();

$typeNames = ['tshirt' => 'T-Shirt', 'hoodie' => 'Hoodie', 'polo' => 'Polo'];

$statusLabels = [
    'pending_payment'  => ['label' => 'Pending Payment',  'class' => 'warning'],
    'payment_uploaded' => ['label' => 'Payment Uploaded', 'class' => 'info'],
    'payment_verified' => ['label' => 'Payment Verified', 'class' => 'success'],
    'processing'       => ['label' => 'Processing',       'class' => 'primary'],
    'printing'         => ['label' => 'Printing',         'class' => 'primary'],
    'ready_pickup'     => ['label' => 'Ready for Pickup', 'class' => 'success'],
    'delivered'        => ['label' => 'Delivered',         'class' => 'success'],
    'cancelled'        => ['label' => 'Cancelled',         'class' => 'danger'],
];
?>

<?php include 'includes/header/header.php'; ?>

<style>
.custom-orders-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 2rem 1rem;
}
.custom-orders-header {
    margin-bottom: 2rem;
}
.custom-orders-header h1 {
    font-size: 2rem;
    font-weight: 800;
}
.custom-order-card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #eee;
    box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    margin-bottom: 1rem;
    transition: all 0.2s;
    overflow: hidden;
}
.custom-order-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}
.custom-order-row {
    display: grid;
    grid-template-columns: 100px 1fr 150px 140px;
    gap: 1rem;
    align-items: center;
    padding: 1rem 1.25rem;
}
.custom-order-img {
    width: 80px;
    height: 80px;
    border-radius: 10px;
    object-fit: cover;
    border: 1px solid #eee;
    background: #f8f9fa;
}
.custom-order-info h6 {
    font-weight: 700;
    margin-bottom: 0.2rem;
    font-size: 0.95rem;
}
.custom-order-info p {
    color: #888;
    font-size: 0.82rem;
    margin: 0;
}
.custom-order-price {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--accent-green, #2d6a4f);
    text-align: center;
}
.custom-order-actions {
    text-align: center;
}
.custom-order-actions .status-badge {
    display: inline-block;
    padding: 0.3rem 0.7rem;
    border-radius: 8px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
}
.status-badge.warning { background: #fff3cd; color: #856404; }
.status-badge.info { background: #d1ecf1; color: #0c5460; }
.status-badge.success { background: #d4edda; color: #155724; }
.status-badge.primary { background: #cce5ff; color: #004085; }
.status-badge.danger { background: #f8d7da; color: #721c24; }
.btn-track-sm {
    display: inline-block;
    padding: 0.4rem 0.8rem;
    background: var(--accent-green, #2d6a4f);
    color: #fff;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.2s;
}
.btn-track-sm:hover {
    background: #245a42;
    color: #fff;
}
.empty-orders {
    text-align: center;
    padding: 3rem;
}
.empty-orders i {
    font-size: 3rem;
    color: #ddd;
    margin-bottom: 1rem;
}
@media (max-width: 768px) {
    .custom-order-row {
        grid-template-columns: 80px 1fr;
    }
    .custom-order-price,
    .custom-order-actions {
        grid-column: 1 / -1;
        text-align: left;
    }
}
</style>

<div class="custom-orders-container">
    <div class="custom-orders-header">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb" style="font-size:0.85rem;">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="orders.php" class="text-decoration-none">Orders</a></li>
                <li class="breadcrumb-item active">Custom Orders</li>
            </ol>
        </nav>
        <h1><i class="fas fa-shirt me-2"></i>My Custom Orders</h1>
        <p class="text-muted">Track all your custom apparel orders</p>
    </div>

    <?php if ($orders && $orders->num_rows > 0): ?>
        <?php while ($o = $orders->fetch_assoc()): 
            $statusInfo = $statusLabels[$o['status']] ?? ['label' => $o['status'], 'class' => 'secondary'];
        ?>
            <div class="custom-order-card">
                <div class="custom-order-row">
                    <div>
                        <img src="<?php echo htmlspecialchars($o['design_image']); ?>" class="custom-order-img" alt="Design" onerror="this.src='https://placehold.co/80x80/f0f0f0/999?text=Design'">
                    </div>
                    <div class="custom-order-info">
                        <h6>Custom <?php echo htmlspecialchars($typeNames[$o['product_type']] ?? 'Apparel'); ?></h6>
                        <p>Order #<?php echo (int)$o['id']; ?> · Size: <?php echo htmlspecialchars($o['size']); ?> · Qty: <?php echo (int)$o['quantity']; ?></p>
                        <p><?php echo date('M d, Y h:i A', strtotime($o['created_at'])); ?>
                            <?php if ($o['payment_method']): ?>
                                · <?php echo strtoupper(htmlspecialchars($o['payment_method'])); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="custom-order-price">
                        ₱<?php echo number_format($o['total_price'], 2); ?>
                    </div>
                    <div class="custom-order-actions">
                        <span class="status-badge <?php echo $statusInfo['class']; ?>"><?php echo $statusInfo['label']; ?></span>
                        <br>
                        <?php if ($o['status'] === 'pending_payment'): ?>
                            <a href="custom-payment.php?order_id=<?php echo (int)$o['id']; ?>" class="btn-track-sm" style="background:#e67e22;">
                                <i class="fas fa-credit-card me-1"></i>Pay Now
                            </a>
                        <?php else: ?>
                            <a href="custom-order-tracking.php?order_id=<?php echo (int)$o['id']; ?>" class="btn-track-sm">
                                <i class="fas fa-truck me-1"></i>Track
                            </a>
                            <a href="custom-invoice.php?order_id=<?php echo (int)$o['id']; ?>" class="btn-track-sm" style="background:#1a1a1a;margin-top:4px;" target="_blank">
                                <i class="fas fa-file-invoice me-1"></i>Invoice
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="custom-order-card">
            <div class="empty-orders">
                <i class="fas fa-shirt d-block"></i>
                <h5 class="text-muted">No custom orders yet</h5>
                <p class="text-muted">Create your first custom design and place an order!</p>
                <a href="custom-design.php" class="btn-track-sm" style="padding:0.6rem 1.5rem;">
                    <i class="fas fa-palette me-1"></i>Start Designing
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer/footer.php'; ?>
