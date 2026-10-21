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
        } elseif ($action === 'delete_order') {
            // Permanent delete, for cancelled orders only. Cancelling already
            // returned the stock and vouchers, so nothing is lost but the row.
            $o = $conn->prepare("SELECT * FROM orders WHERE id = ?");
            $o->bind_param("i", $order_id);
            $o->execute();
            $ord = $o->get_result()->fetch_assoc();
            $o->close();
            $hasTable = fn($t) => (($r = $conn->query("SHOW TABLES LIKE '$t'")) && $r->num_rows > 0);

            $paidOnline = false;
            if ($ord && $hasTable('paymongo_sessions')) {
                $p = $conn->prepare("SELECT 1 FROM paymongo_sessions WHERE order_kind = 'order' AND order_id = ? AND status = 'paid' LIMIT 1");
                $p->bind_param("i", $order_id);
                $p->execute();
                $paidOnline = $p->get_result()->num_rows > 0;
                $p->close();
            }

            if (!$ord) {
                $error = 'Order not found. It may have been deleted already.';
            } elseif ($ord['status'] !== 'cancelled') {
                $error = 'Only cancelled orders can be deleted. Cancel the order first.';
            } elseif ($paidOnline || in_array($ord['payment_status'] ?? 'unpaid', ['verified', 'pending_verification'], true)) {
                // The payment record must stay for refunds and disputes.
                $error = 'This order has a payment on record, so it is kept for the payment history.';
            } else {
                $hasSubs = $hasTable('payment_submissions');
                $proofs  = [(string) ($ord['payment_proof'] ?? '')];
                if ($hasSubs) {
                    $ps = $conn->prepare("SELECT proof_path FROM payment_submissions WHERE order_id = ?");
                    $ps->bind_param("i", $order_id);
                    $ps->execute();
                    foreach ($ps->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $proofs[] = (string) $row['proof_path'];
                    $ps->close();
                }

                // Rows that point at the order without a foreign key go with it,
                // so nothing is left referring to an order that no longer exists.
                // order_items and gcash_transactions follow by ON DELETE CASCADE.
                $cleanup = [];
                if ($hasSubs) $cleanup[] = "DELETE FROM payment_submissions WHERE order_id = ?";
                if ($hasTable('paymongo_sessions')) $cleanup[] = "DELETE FROM paymongo_sessions WHERE order_kind = 'order' AND order_id = ?";
                if ($hasTable('user_coupons')) {
                    $uc = $conn->query("SHOW COLUMNS FROM user_coupons LIKE 'released_order_id'");
                    if ($uc && $uc->num_rows > 0) $cleanup[] = "UPDATE user_coupons SET released_order_id = NULL WHERE released_order_id = ?";
                }

                $conn->begin_transaction();
                try {
                    foreach ($cleanup as $sql) {
                        $c = $conn->prepare($sql);
                        $c->bind_param("i", $order_id);
                        $c->execute();
                        $c->close();
                    }
                    // Status re-checked in the DELETE itself, in case it was un-cancelled meanwhile.
                    $d = $conn->prepare("DELETE FROM orders WHERE id = ? AND status = 'cancelled'");
                    $d->bind_param("i", $order_id);
                    $d->execute();
                    $gone = $d->affected_rows === 1;
                    $d->close();
                    if (!$gone) throw new Exception('Only cancelled orders can be deleted.');
                    $conn->commit();
                } catch (Throwable $e) {
                    $conn->rollback();
                    error_log('[orders] delete failed: ' . $e->getMessage());
                    $gone = false;
                    $error = 'Could not delete this order. ' . ($e instanceof Exception && !($e instanceof mysqli_sql_exception) ? $e->getMessage() : 'Please try again.');
                }

                if ($gone) {
                    // Files only after the commit: a rolled-back delete keeps its proof.
                    require_once '../includes/payment-proof-handler.php';
                    foreach (array_unique(array_filter($proofs)) as $rel) {
                        // Older orders kept the screenshot under uploads/payments/ (web root).
                        $abs = preg_match('#^uploads/payments/[\w.-]+$#', $rel)
                            ? dirname(__DIR__) . '/' . $rel
                            : paymentResolveProofPath($rel);
                        if ($abs !== null && is_file($abs)) @unlink($abs);
                    }
                    $dir = PAYMENT_PROOF_STORAGE . DIRECTORY_SEPARATOR . (int) $order_id;
                    if (is_dir($dir)) @rmdir($dir);   // only succeeds when empty
                    logAudit('order_deleted', 'order', $order_id,
                        'Cancelled order #' . (int) $order_id . ' (₱' . number_format((float) $ord['total'], 2) . ') permanently deleted');
                    $success = 'Order #' . (int) $order_id . ' was permanently deleted.';
                }
            }
        } else {
            // Default: update order status
            $allowed = ['pending','confirmed','preparing','out_for_delivery','completed','cancelled'];
            $status  = $_POST['status'] ?? '';
            if (!in_array($status, $allowed, true)) {
                $error = 'Invalid status value.';
            } else {
                $old = $conn->prepare("SELECT status FROM orders WHERE id = ?");
                $old->bind_param("i", $order_id);
                $old->execute();
                $oldStatus = ($old->get_result()->fetch_row() ?: [null])[0];
                $old->close();
                // Cancelling puts the stock back; un-cancelling takes back the
                // stock and the vouchers the cancel returned. All or nothing.
                $uncancel = $oldStatus === 'cancelled' && $status !== 'cancelled';
                $cancel   = $oldStatus !== 'cancelled' && $status === 'cancelled';
                $tx       = $uncancel || $cancel;
                $why      = '';
                if ($tx) {
                    // Their ALTERs would commit the transaction early.
                    $stockOn = orderStockReady();
                    if ($uncancel) vouchersReady();
                    $conn->begin_transaction();
                    if ($uncancel) $why = voucherReclaim((int)$order_id);
                    if ($why === '' && $stockOn) {
                        if ($cancel) {
                            returnOrderStock($order_id);
                        } elseif (!takeBackOrderStock($order_id)) {
                            $why = 'Not enough stock left to restore this order. Restock the items first, then try again.';
                        }
                    }
                    if ($why !== '') $conn->rollback();
                }
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $status, $order_id);
                if ($why !== '') {
                    $error = $why;
                } elseif ($stmt->execute()) {
                    if ($tx) $conn->commit();
                    // Auto-mark COD orders as paid (verified) once completed
                    if ($status === 'completed' && $hasPaymentStatusCol) {
                        $conn->query("UPDATE orders SET payment_status = 'verified'
                                      WHERE id = " . (int)$order_id . "
                                      AND LOWER(payment_method) = 'cod'
                                      AND payment_status = 'unpaid'");
                    }
                    if ($status === 'cancelled') {
                        voucherRelease((int)$order_id);
                    }
                    require_once '../includes/email-helper.php';
                    sendOrderStatusEmail($conn, $order_id, $status);
                    logAudit('order_status_updated', 'order', $order_id, "Status changed to: $status");
                    $success = 'Order status updated successfully!';
                } else {
                    if ($tx) $conn->rollback();
                    $error = 'Failed to update order status!';
                }
                $stmt->close();
            }
        }
    }
}

// Status changed from a <form data-ajax>: answer with JSON, not the page.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjaxForm()) {
    ajaxFormReply($error === '', $error ?: $success);
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
                    <tr data-live="order-<?php echo (int)$order['id']; ?>">
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
                                    <form method="POST" class="d-inline" data-ajax onsubmit="return confirm('Verify this payment?');">
                                        <?php echo csrfTokenField(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                        <input type="hidden" name="action" value="verify_payment">
                                        <button type="submit" class="btn btn-success btn-sm" title="Approve"><i class="fas fa-check"></i></button>
                                    </form>
                                    <form method="POST" class="d-inline" data-ajax onsubmit="return confirm('Reject this payment?');">
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
                            <form method="POST" class="d-inline" data-ajax>
                                <?php echo csrfTokenField(); ?>
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="action" value="update_status">
                                <select name="status" class="form-select form-select-sm" onchange="this.form.requestSubmit();" style="max-width: 140px;" aria-label="Status of order #<?php echo (int)$order['id']; ?>">
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
                            <?php if ($order['status'] === 'cancelled' && !in_array($order['payment_status'] ?? 'unpaid', ['verified', 'pending_verification'], true)): ?>
                                <form method="POST" class="d-inline" data-ajax
                                      onsubmit="return confirm('Permanently delete Order #<?php echo (int)$order['id']; ?>? This cannot be undone.');">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                    <input type="hidden" name="action" value="delete_order">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete order" aria-label="Delete order #<?php echo (int)$order['id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
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
