<?php
require 'includes/config.php';
require_once 'includes/payment-config.php';   // paymentMethodLabel()
redirectToLogin();

$pageTitle = 'Order Details';

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id === 0) {
    header("Location: orders.php");
    exit();
}

// Get order details - check if user is owner or admin
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND (user_id = ? OR ? = 'admin')");
$user_type = $_SESSION['user_type'] ?? '';
$stmt->bind_param("iis", $order_id, $_SESSION['user_id'], $user_type);
$stmt->execute();
$order_result = $stmt->get_result();

if ($order_result->num_rows === 0) {
    $_SESSION['error'] = 'Order not found or you do not have permission to view this order.';
    header("Location: orders.php");
    exit();
}

$order = $order_result->fetch_assoc();
$stmt->close();

// Get order items
$items_stmt = $conn->prepare("SELECT oi.*, p.name, p.category FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
?>

<?php include 'includes/header/header.php'; ?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="orders.php">Orders</a></li>
                    <li class="breadcrumb-item active">Order #<?php echo htmlspecialchars($order['id']); ?></li>
                </ol>
            </nav>
            <div class="card" style="border: 1px solid var(--border-light); border-radius: var(--radius-md);">
                <div class="card-body p-4">
                    <h5 class="mb-4" style="font-weight: 700;">Order #<?php echo htmlspecialchars($order['id']); ?></h5>
                    <!-- Order Status -->
                    <div class="row mb-4 pb-4 border-bottom">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Order Status</h6>
                            <span class="badge bg-<?php echo $order['status'] === 'completed' ? 'success' : ($order['status'] === 'pending' ? 'warning' : ($order['status'] === 'cancelled' ? 'danger' : 'info')); ?>" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                <i class="fas fa-circle"></i> <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                            </span>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <h6 class="text-muted mb-2">Order Date</h6>
                            <p><?php echo date('F d, Y H:i A', strtotime($order['created_at'])); ?></p>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="mb-4">
                        <h6 class="mb-3" style="font-weight: 700;">Order Items</h6>
                        <div class="table-responsive">
                            <table class="table" style="border: none;">
                                <thead style="background-color: #f8f7f4;">
                                    <tr>
                                        <th>Item</th>
                                        <th style="text-align: right;">Color</th>
                                        <th style="text-align: right;">Size</th>
                                        <th style="text-align: right;">Qty</th>
                                        <th style="text-align: right;">Unit Price</th>
                                        <th style="text-align: right;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($item = $items_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($item['category']); ?></small>
                                        </td>
                                        <td style="text-align: right;"><?php echo htmlspecialchars($item['color'] ?? 'N/A'); ?></td>
                                        <td style="text-align: right;"><?php echo htmlspecialchars($item['size'] ?? 'N/A'); ?></td>
                                        <td style="text-align: right;"><?php echo $item['quantity']; ?></td>
                                        <td style="text-align: right;">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td style="text-align: right; font-weight: 700;">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="bg-light p-3 rounded mb-4">
                        <div class="row mb-2">
                            <div class="col-md-6">Subtotal:</div>
                            <div class="col-md-6 text-end">₱<?php echo number_format($order['subtotal'], 2); ?></div>
                        </div>
                        <?php if ($order['discount_amount'] > 0): ?>
                        <div class="row mb-2">
                            <div class="col-md-6">
                                Discount (<span style="font-weight: 700;"><?php echo strtoupper($order['discount_type']); ?></span>):
                            </div>
                            <div class="col-md-6 text-end" style="color: var(--primary); font-weight: 700;">
                                -₱<?php echo number_format($order['discount_amount'], 2); ?>
                            </div>
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
                        <div class="row mb-2">
                            <div class="col-md-6">VAT (12%):</div>
                            <div class="col-md-6 text-end">₱<?php echo number_format($vatShown, 2); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="row mb-2">
                            <div class="col-md-6">Delivery Fee:</div>
                            <div class="col-md-6 text-end">₱<?php echo number_format($order['delivery_fee'], 2); ?></div>
                        </div>
                        <div class="row" style="border-top: 2px solid #ddd; padding-top: 1rem; margin-top: 1rem;">
                            <div class="col-md-6" style="font-weight: 700; font-size: 1.1rem;">Total Amount:</div>
                            <div class="col-md-6 text-end" style="font-weight: 700; font-size: 1.1rem; color: var(--coffee-dark);">
                                ₱<?php echo number_format($order['total'], 2); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Delivery Information -->
                    <div class="mb-4 pb-4 border-bottom">
                        <h6 class="mb-3" style="font-weight: 700;">Delivery Information</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="text-muted mb-1">Payment Method</p>
                                <p style="color: var(--coffee-dark); font-weight: 600;">
                                    <i class="fas fa-<?php echo $order['payment_method'] === 'cod' ? 'money-bill' : 'credit-card'; ?>"></i>
                                    <?php echo htmlspecialchars(paymentMethodLabel($order['payment_method'])); ?>
                                </p>
                            </div>
                            <?php if ($order['payment_reference']): ?>
                            <div class="col-md-6">
                                <p class="text-muted mb-1">Payment Reference</p>
                                <p style="color: var(--coffee-dark); font-weight: 600;"><?php echo htmlspecialchars($order['payment_reference']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted mb-1">Delivery Address</p>
                        <p style="color: var(--coffee-dark); font-weight: 600;">
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($order['delivery_address']); ?>
                        </p>
                    </div>

                    <!-- Special Instructions -->
                    <?php if ($order['notes']): ?>
                    <div class="mb-4">
                        <h6 class="mb-2" style="font-weight: 700;">Special Instructions</h6>
                        <p class="text-muted"><?php echo htmlspecialchars($order['notes']); ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Timeline/Status History (placeholder) -->
                    <div class="mb-4">
                        <h6 class="mb-3" style="font-weight: 700;">Status Timeline</h6>
                        <div style="padding-left: 1rem; border-left: 3px solid var(--primary);">
                            <div class="mb-3">
                                <p class="mb-0" style="color: var(--primary); font-weight: 700;">
                                    <i class="fas fa-check-circle"></i> Order Placed
                                </p>
                                <small class="text-muted"><?php echo date('F d, Y H:i A', strtotime($order['created_at'])); ?></small>
                            </div>
                            <?php if ($order['status'] !== 'pending'): ?>
                            <div class="mb-3">
                                <p class="mb-0" style="color: var(--primary); font-weight: 700;">
                                    <i class="fas fa-check-circle"></i> Order <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                </p>
                                <small class="text-muted"><?php echo date('F d, Y H:i A', strtotime($order['updated_at'])); ?></small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Back Button -->
            <div class="text-center mt-4">
                <a href="orders.php" class="btn btn-outline-dark" style="border-radius: var(--radius-sm); padding: 0.5rem 1.5rem;">
                    <i class="fas fa-arrow-left" style="margin-right: 0.5rem;"></i> Back to Orders
                </a>
                <a href="invoice.php?order_id=<?php echo (int)$order['id']; ?>" class="btn btn-dark" target="_blank" style="border-radius: var(--radius-sm); padding: 0.5rem 1.5rem;">
                    <i class="fas fa-file-pdf" style="margin-right: 0.5rem;"></i> Download Invoice
                </a>
                <?php if ($_SESSION['user_type'] === 'admin'): ?>
                <a href="admin/orders.php" class="btn btn-outline-dark" style="border-radius: var(--radius-sm); padding: 0.5rem 1.5rem;">
                    <i class="fas fa-cogs" style="margin-right: 0.5rem;"></i> Manage Order
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer/footer.php'; ?>
