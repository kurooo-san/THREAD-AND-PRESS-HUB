<?php
require 'includes/config.php';
redirectToLogin();

$pageTitle = 'My Orders';

// Get user orders
$stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$orders_result = $stmt->get_result();
?>

<?php include 'includes/header/header.php'; ?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb" style="font-size:0.85rem;">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active">Orders</li>
        </ol>
    </nav>
    <h1 style="font-weight:800; font-size:2rem; margin-bottom:1.5rem;">My Orders</h1>

    <?php if ($orders_result->num_rows > 0): ?>
        <div class="row">
            <?php while ($order = $orders_result->fetch_assoc()): ?>
                <div class="col-md-12 mb-4">
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-id">Order #<?php echo htmlspecialchars($order['id']); ?></div>
                                <small class="text-muted"><?php echo date('F d, Y H:i A', strtotime($order['created_at'])); ?></small>
                            </div>
                            <div class="text-end">
                                <div class="order-status status-<?php echo htmlspecialchars($order['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                                </div>
                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-<?php echo $order['payment_method'] === 'cod' ? 'money-bill' : 'mobile-alt'; ?>"></i>
                                    <?php 
                                        $payLabels = ['gcash' => 'GCash', 'maya' => 'Maya', 'cod' => 'Cash on Delivery'];
                                        echo $payLabels[$order['payment_method']] ?? ucfirst($order['payment_method']);
                                    ?>
                                </small>
                            </div>
                        </div>

                        <div class="order-items">
                            <?php
                            // Get order items
                            $items_stmt = $conn->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
                            $items_stmt->bind_param("i", $order['id']);
                            $items_stmt->execute();
                            $items_result = $items_stmt->get_result();
                            
                            while ($item = $items_result->fetch_assoc()):
                            ?>
                                <div class="order-item">
                                    <span>
                                        <?php echo htmlspecialchars($item['name']); ?> x<?php echo $item['quantity']; ?>
                                        <?php if (!empty($item['color']) || !empty($item['size'])): ?>
                                            <br><small class="text-muted">
                                                <?php if (!empty($item['color'])) echo 'Color: ' . htmlspecialchars($item['color']); ?>
                                                <?php if (!empty($item['size'])) echo ' | Size: ' . htmlspecialchars($item['size']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </span>
                                    <span>₱<?php echo number_format($item['subtotal'], 2); ?></span>
                                </div>
                            <?php endwhile;
                            $items_stmt->close();
                            ?>
                        </div>

                        <div style="border-top: 1px solid #eee; padding-top: 1rem; margin-top: 1rem;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span class="text-muted">Subtotal:</span>
                                <span>₱<?php echo number_format($order['subtotal'], 2); ?></span>
                            </div>
                            <?php if ($order['discount_amount'] > 0): ?>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span class="text-muted">Discount (<?php echo strtoupper($order['discount_type']); ?>):</span>
                                <span style="color: var(--accent-green);">-₱<?php echo number_format($order['discount_amount'], 2); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php
                            // VAT as the reconciling difference — breakdown always sums
                            // to the stored total; pre-VAT orders show no row.
                            $vatShown = (float)$order['total']
                                - ((float)$order['subtotal'] - (float)$order['discount_amount'] - (float)($order['coupon_discount'] ?? 0))
                                - (float)$order['delivery_fee'];
                            if ($vatShown > 0.009):
                            ?>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span class="text-muted">VAT (12%):</span>
                                <span>₱<?php echo number_format($vatShown, 2); ?></span>
                            </div>
                            <?php endif; ?>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span class="text-muted">Delivery Fee:</span>
                                <span>₱<?php echo number_format($order['delivery_fee'], 2); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 1.1rem; color: var(--coffee-dark);">
                                <span>Total:</span>
                                <span>₱<?php echo number_format($order['total'], 2); ?></span>
                            </div>
                        </div>

                        <div class="mt-3">
                            <small class="text-muted"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($order['delivery_address']); ?></small>
                        </div>

                        <div class="mt-3">
                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-dark" style="border-radius:8px;">
                                View Details <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                            <a href="invoice.php?order_id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;" target="_blank">
                                <i class="fas fa-file-pdf"></i> Invoice
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-receipt"></i>
            <h5>No orders yet</h5>
            <p>When you place orders, they'll appear here</p>
            <a href="shop.php" class="btn btn-dark" style="border-radius:12px;">Start Shopping</a>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer/footer.php'; ?>
