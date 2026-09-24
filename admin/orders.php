<?php
require '../includes/config.php';
require_once '../includes/payment-config.php';   // paymentMethodLabel()

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Manage Orders';
$error = '';
$success = '';

// Detect optional payment_status column
$hasPaymentStatusCol = false;
$psCheck = $conn->query("SHOW COLUMNS FROM orders LIKE 'payment_status'");
if ($psCheck && $psCheck->num_rows > 0) { $hasPaymentStatusCol = true; }

// Handle status update / payment verification (CSRF-protected)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } else {
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $action   = $_POST['action'] ?? 'update_status';

        if ($order_id <= 0) {
            $error = 'Invalid order.';
        } elseif ($action === 'verify_payment' && $hasPaymentStatusCol) {
            $stmt = $conn->prepare("UPDATE orders SET payment_status = 'verified', status = CASE WHEN status = 'pending' THEN 'confirmed' ELSE status END WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            if ($stmt->execute()) {
                // Mark transaction record as confirmed
                $conn->query("UPDATE gcash_transactions SET status = 'confirmed' WHERE order_id = " . (int)$order_id);
                logAudit('payment_verified', 'order', $order_id, 'Payment verified by admin');
                $success = 'Payment verified and order confirmed.';
            } else {
                $error = 'Failed to verify payment.';
            }
            $stmt->close();
        } elseif ($action === 'reject_payment' && $hasPaymentStatusCol) {
            $stmt = $conn->prepare("UPDATE orders SET payment_status = 'rejected' WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            if ($stmt->execute()) {
                $conn->query("UPDATE gcash_transactions SET status = 'rejected' WHERE order_id = " . (int)$order_id);
                logAudit('payment_rejected', 'order', $order_id, 'Payment rejected by admin');
                $success = 'Payment marked as rejected.';
            } else {
                $error = 'Failed to update payment.';
            }
            $stmt->close();
        } else {
            // Default: update order status
            $allowed = ['pending','confirmed','preparing','out_for_delivery','completed','cancelled'];
            $status  = $_POST['status'] ?? '';
            if (!in_array($status, $allowed, true)) {
                $error = 'Invalid status value.';
            } else {
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $status, $order_id);
                if ($stmt->execute()) {
                    // Auto-mark COD orders as paid (verified) once completed
                    if ($status === 'completed' && $hasPaymentStatusCol) {
                        $conn->query("UPDATE orders SET payment_status = 'verified'
                                      WHERE id = " . (int)$order_id . "
                                      AND LOWER(payment_method) = 'cod'
                                      AND payment_status = 'unpaid'");
                    }
                    require_once '../includes/email-helper.php';
                    sendOrderStatusEmail($conn, $order_id, $status);
                    logAudit('order_status_updated', 'order', $order_id, "Status changed to: $status");
                    $success = 'Order status updated successfully!';
                } else {
                    $error = 'Failed to update order status!';
                }
                $stmt->close();
            }
        }
    }
}

// Get all orders
$selectExtras = $hasPaymentStatusCol ? ', o.payment_status' : '';
$orders = $conn->query("SELECT o.*$selectExtras, u.fullname, u.email FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC");
?>

<?php include '../includes/header/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="admin-container">
    <div class="mb-4">
        <h1 class="text-coffee-dark mb-2" style="font-size: 2rem; font-weight: 800;">
            <i class="fas fa-receipt"></i> Manage Orders
        </h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="admin-card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <?php if ($hasPaymentStatusCol): ?><th>Pay. Status</th><?php endif; ?>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($order = $orders->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?php echo $order['id']; ?></strong></td>
                        <td>
                            <div><?php echo htmlspecialchars($order['fullname']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                        </td>
                        <td>₱<?php echo number_format($order['total'], 2); ?></td>
                        <td>
                            <?php echo htmlspecialchars(paymentMethodLabel($order['payment_method'])); ?>
                            <?php if (!empty($order['payment_reference'])): ?>
                                <br><small class="text-muted">Ref: <?php echo htmlspecialchars($order['payment_reference']); ?></small>
                            <?php endif; ?>
                        </td>
                        <?php if ($hasPaymentStatusCol): ?>
                        <td>
                            <?php
                                $ps = $order['payment_status'] ?? 'unpaid';
                                $psBadge = ['unpaid'=>'secondary','pending_verification'=>'warning text-dark','verified'=>'success','rejected'=>'danger'][$ps] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $psBadge; ?>"><?php echo ucfirst(str_replace('_',' ',$ps)); ?></span>
                            <?php // Any non-cash channel can land here (e-wallet, InstaPay, bank transfer). ?>
                            <?php if ($ps === 'pending_verification' && $order['payment_method'] !== 'cod'): ?>
                                <div class="btn-group btn-group-sm mt-1" role="group">
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Verify this payment?');">
                                        <?php echo csrfTokenField(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                        <input type="hidden" name="action" value="verify_payment">
                                        <button type="submit" class="btn btn-success btn-sm" title="Approve"><i class="fas fa-check"></i></button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Reject this payment?');">
                                        <?php echo csrfTokenField(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                        <input type="hidden" name="action" value="reject_payment">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Reject"><i class="fas fa-times"></i></button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td>
                            <form method="POST" class="d-inline">
                                <?php echo csrfTokenField(); ?>
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="action" value="update_status">
                                <select name="status" class="form-select form-select-sm" onchange="this.form.submit();" style="max-width: 140px;">
                                    <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="preparing" <?php echo $order['status'] === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                    <option value="out_for_delivery" <?php echo $order['status'] === 'out_for_delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                                    <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </form>
                        </td>
                        <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                        <td>
                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="../invoice.php?order_id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="Download Invoice PDF">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->
<script src="../js/admin-sidebar.js"></script>

<?php include '../includes/footer/footer.php'; ?>
