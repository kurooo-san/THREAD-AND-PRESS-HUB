<?php
require '../includes/config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Order Details';

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id === 0) {
    header("Location: orders.php");
    exit();
}

// Get order details
$stmt = $conn->prepare("SELECT o.*, u.fullname, u.email, u.phone FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_result = $stmt->get_result();

if ($order_result->num_rows === 0) {
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

<?php include '../includes/header/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="admin-container">
    <div class="mb-4">
        <h1 class="text-coffee-dark mb-2" style="font-size: 2rem; font-weight: 800;">
            <i class="fas fa-receipt"></i> Order #<?php echo htmlspecialchars($order['id']); ?>
        </h1>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Order Details -->
            <div class="admin-card mb-4">
                <h5 class="text-coffee-dark mb-4" style="font-weight: 700;">Order Information</h5>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p class="text-muted mb-1">Customer</p>
                        <p style="color: var(--coffee-dark); font-weight: 600;"><?php echo htmlspecialchars($order['fullname']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p class="text-muted mb-1">Email</p>
                        <p style="color: var(--coffee-dark); font-weight: 600;"><?php echo htmlspecialchars($order['email']); ?></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p class="text-muted mb-1">Phone</p>
                        <p style="color: var(--coffee-dark); font-weight: 600;"><?php echo htmlspecialchars($order['phone'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p class="text-muted mb-1">Order Date</p>
                        <p style="color: var(--coffee-dark); font-weight: 600;"><?php echo date('F d, Y H:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="admin-card mb-4">
                <h5 class="text-coffee-dark mb-4" style="font-weight: 700;">Order Items</h5>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
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

            <!-- Delivery Address -->
            <div class="admin-card mb-4">
                <h5 class="text-coffee-dark mb-3" style="font-weight: 700;">Delivery Address</h5>
                <div style="background-color: #f8f7f4; padding: 1rem; border-radius: 8px;">
                    <p><?php echo htmlspecialchars($order['delivery_address']); ?></p>
                </div>
                <?php if ($order['notes']): ?>
                <div class="mt-3">
                    <h6 class="text-coffee-dark mb-2" style="font-weight: 700;">Special Instructions</h6>
                    <p class="text-muted"><?php echo htmlspecialchars($order['notes']); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Order Summary -->
            <div class="admin-card mb-4">
                <h5 class="text-coffee-dark mb-4" style="font-weight: 700;">Order Summary</h5>
                <div style="background-color: #f8f7f4; padding: 1.5rem; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #ddd;">
                        <span class="text-muted">Subtotal:</span>
                        <span>₱<?php echo number_format($order['subtotal'], 2); ?></span>
                    </div>
                    <?php if ($order['discount_amount'] > 0): ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #ddd;">
                        <span class="text-muted">Discount (<?php echo strtoupper($order['discount_type']); ?>):</span>
                        <span style="color: var(--accent-green); font-weight: 700;">-₱<?php echo number_format($order['discount_amount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php
                    // VAT shown as the reconciling difference so the breakdown always
                    // sums exactly to the stored total (pre-VAT orders: 0 → hidden).
                    $vatShown = (float)$order['total']
                        - ((float)$order['subtotal'] - (float)$order['discount_amount'] - (float)($order['coupon_discount'] ?? 0))
                        - (float)$order['delivery_fee'];
                    if ($vatShown > 0.009):
                    ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #ddd;">
                        <span class="text-muted">VAT (12%):</span>
                        <span>₱<?php echo number_format($vatShown, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #ddd;">
                        <span class="text-muted">Delivery Fee:</span>
                        <span>₱<?php echo number_format($order['delivery_fee'], 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 1.1rem; color: var(--coffee-dark);">
                        <span>Total:</span>
                        <span>₱<?php echo number_format($order['total'], 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment Information -->
            <div class="admin-card mb-4">
                <h5 class="text-coffee-dark mb-3" style="font-weight: 700;">Payment Information</h5>
                <p class="text-muted mb-2">Payment Method</p>
                <p style="color: var(--coffee-dark); font-weight: 600; margin-bottom: 1.5rem;">
                    <i class="fas fa-<?php echo $order['payment_method'] === 'cod' ? 'money-bill' : 'mobile-alt'; ?>"></i>
                    <?php 
                        echo htmlspecialchars(paymentMethodLabel($order['payment_method']));
                    ?>
                </p>
                <?php if ($order['payment_reference']): ?>
                <p class="text-muted mb-2">Reference Number</p>
                <p style="color: var(--coffee-dark); font-weight: 600;"><?php echo htmlspecialchars($order['payment_reference']); ?></p>
                <?php endif; ?>
                <?php if (!empty($order['payment_proof'])): ?>
                <p class="text-muted mb-2 mt-3">Payment Screenshot</p>
                <a href="../<?php echo htmlspecialchars($order['payment_proof']); ?>" target="_blank">
                    <img src="../<?php echo htmlspecialchars($order['payment_proof']); ?>"
                         alt="Payment Proof"
                         style="max-width:100%; max-height:300px; border:1px solid #ddd; border-radius:8px; cursor:zoom-in;">
                </a>
                <p class="text-muted mt-2" style="font-size:0.8rem;"><i class="fas fa-info-circle"></i> Click image to view full size</p>
                <?php endif; ?>
            </div>

            <!-- Order Status -->
            <div class="admin-card">
                <h5 class="text-coffee-dark mb-3" style="font-weight: 700;">Order Status</h5>
                <form method="POST" action="orders.php">
                    <?php echo csrfTokenField(); ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <select name="status" class="form-control mb-2">
                        <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="preparing" <?php echo $order['status'] === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                        <option value="out_for_delivery" <?php echo $order['status'] === 'out_for_delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                        <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <a href="orders.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left"></i> Back to Orders
        </a>
    </div>
</div>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->
<script src="../js/admin-sidebar.js"></script>

<?php include '../includes/footer/footer.php'; ?>
