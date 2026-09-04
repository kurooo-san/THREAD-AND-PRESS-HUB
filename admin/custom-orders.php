<?php
require '../includes/config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Custom Orders Management';
$error = '';
$success = '';

// Run migration if tables don't exist
$tableCheck = $conn->query("SHOW TABLES LIKE 'custom_orders'");
if ($tableCheck->num_rows === 0) {
    $migrationSQL = file_get_contents(__DIR__ . '/../migrate_custom_orders.sql');
    if ($migrationSQL) {
        $conn->multi_query($migrationSQL);
        while ($conn->next_result()) {;}
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $updateOrderId = intval($_POST['order_id']);
    $newStatus = $conn->real_escape_string(trim($_POST['status'] ?? ''));
    $adminNotes = $conn->real_escape_string(trim($_POST['admin_notes'] ?? ''));

    $allowedStatuses = ['pending_payment','payment_uploaded','payment_verified','processing','printing','ready_pickup','delivered','cancelled'];
    if (in_array($newStatus, $allowedStatuses)) {
        $stmt = $conn->prepare("UPDATE custom_orders SET status = ?, admin_notes = ? WHERE id = ?");
        $stmt->bind_param("ssi", $newStatus, $adminNotes, $updateOrderId);
        if ($stmt->execute()) {
            $success = 'Order #' . $updateOrderId . ' status updated to ' . ucfirst(str_replace('_', ' ', $newStatus)) . '!';

            // If verifying payment, also update payment status
            if ($newStatus === 'payment_verified') {
                $conn->query("UPDATE custom_order_payments SET payment_status = 'verified' WHERE custom_order_id = " . $updateOrderId);
            }

            // Real-time status email to the customer (non-fatal on failure)
            try {
                require_once __DIR__ . '/../includes/email-helper.php';
                sendCustomOrderStatusEmail($conn, $updateOrderId, $newStatus);
            } catch (Throwable $mailEx) {
                error_log('[custom-orders] status email failed: ' . $mailEx->getMessage());
            }
        } else {
            $error = 'Failed to update order status.';
        }
        $stmt->close();
    } else {
        $error = 'Invalid status value.';
    }
}

// Handle payment verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    $paymentId = intval($_POST['payment_id']);
    $paymentAction = $_POST['verify_payment'];
    
    if ($paymentAction === 'verify') {
        $conn->query("UPDATE custom_order_payments SET payment_status = 'verified' WHERE id = " . $paymentId);
        // Get the order id and update order status
        $pResult = $conn->query("SELECT custom_order_id FROM custom_order_payments WHERE id = " . $paymentId);
        if ($pRow = $pResult->fetch_assoc()) {
            $conn->query("UPDATE custom_orders SET status = 'payment_verified' WHERE id = " . intval($pRow['custom_order_id']));
        }
        $success = 'Payment verified successfully!';
    } elseif ($paymentAction === 'reject') {
        $conn->query("UPDATE custom_order_payments SET payment_status = 'rejected' WHERE id = " . $paymentId);
        $success = 'Payment marked as rejected.';
    }
}

// Get filter
$statusFilter = $_GET['status'] ?? '';
$whereClause = '';
if ($statusFilter && in_array($statusFilter, ['pending_payment','payment_uploaded','payment_verified','processing','printing','ready_pickup','delivered','cancelled'])) {
    $whereClause = "WHERE co.status = '" . $conn->real_escape_string($statusFilter) . "'";
}

// Get all custom orders with user info and payment info
$orders = $conn->query("
    SELECT co.*, u.fullname, u.email, 
           cop.payment_method, cop.payment_proof, cop.payment_status, cop.reference_number, cop.id as payment_id
    FROM custom_orders co 
    JOIN users u ON co.user_id = u.id 
    LEFT JOIN custom_order_payments cop ON co.id = cop.custom_order_id
    $whereClause
    ORDER BY co.created_at DESC
");

// Stats
$totalOrders = $conn->query("SELECT COUNT(*) as c FROM custom_orders")->fetch_assoc()['c'] ?? 0;
$pendingPayment = $conn->query("SELECT COUNT(*) as c FROM custom_orders WHERE status IN ('pending_payment','payment_uploaded')")->fetch_assoc()['c'] ?? 0;
$processingOrders = $conn->query("SELECT COUNT(*) as c FROM custom_orders WHERE status IN ('payment_verified','processing','printing')")->fetch_assoc()['c'] ?? 0;
$completedOrders = $conn->query("SELECT COUNT(*) as c FROM custom_orders WHERE status IN ('ready_pickup','delivered')")->fetch_assoc()['c'] ?? 0;
$totalRevenue = $conn->query("SELECT COALESCE(SUM(total_price), 0) as r FROM custom_orders WHERE status NOT IN ('cancelled','pending_payment')")->fetch_assoc()['r'] ?? 0;

$statusLabels = [
    'pending_payment'  => ['label' => 'Pending Payment',  'badge' => 'warning'],
    'payment_uploaded' => ['label' => 'Payment Uploaded', 'badge' => 'info'],
    'payment_verified' => ['label' => 'Payment Verified', 'badge' => 'success'],
    'processing'       => ['label' => 'Processing',       'badge' => 'primary'],
    'printing'         => ['label' => 'Printing',         'badge' => 'primary'],
    'ready_pickup'     => ['label' => 'Ready for Pickup', 'badge' => 'teal'],
    'delivered'        => ['label' => 'Delivered',         'badge' => 'success'],
    'cancelled'        => ['label' => 'Cancelled',         'badge' => 'danger'],
];
$typeNames = ['tshirt' => 'T-Shirt', 'hoodie' => 'Hoodie', 'polo' => 'Polo'];
?>

<?php include '../includes/header/header.php'; ?>

<style>
.admin-custom-orders { max-width: 1300px; margin: 0 auto; padding: 1.5rem; }
.co-stats-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.co-stat-card {
    flex: 1; min-width: 150px; background: #fff; border-radius: 12px;
    padding: 1rem 1.25rem; border: 1px solid #eee; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.co-stat-card .stat-num { font-size: 1.8rem; font-weight: 800; color: #1a1a1a; }
.co-stat-card .stat-text { font-size: 0.78rem; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
.co-filter-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.co-filter-tab {
    padding: 0.4rem 0.9rem; border-radius: 8px; text-decoration: none;
    font-size: 0.8rem; font-weight: 600; border: 1px solid #e0e0e0; color: #666; transition: all 0.2s;
}
.co-filter-tab:hover { border-color: #aaa; color: #333; }
.co-filter-tab.active { background: var(--accent-green,#2d6a4f); color: #fff; border-color: var(--accent-green); }
.co-order-card {
    background: #fff; border-radius: 14px; border: 1px solid #eee;
    box-shadow: 0 2px 10px rgba(0,0,0,0.04); margin-bottom: 1rem; overflow: hidden;
}
.co-order-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
.co-order-grid {
    display: grid; grid-template-columns: 110px 1fr 180px 220px;
    gap: 1rem; align-items: start; padding: 1rem 1.25rem;
}
.co-thumb { width: 90px; height: 90px; border-radius: 10px; object-fit: cover; border: 1px solid #eee; background: #f8f9fa; cursor: pointer; transition: transform 0.2s; }
.co-thumb:hover { transform: scale(1.05); }
.co-info h6 { font-weight: 700; margin-bottom: 0.2rem; font-size: 0.92rem; }
.co-info p { color: #888; font-size: 0.8rem; margin: 0 0 0.1rem; }
.co-badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
.co-badge.warning { background: #fff3cd; color: #856404; }
.co-badge.info { background: #d1ecf1; color: #0c5460; }
.co-badge.success { background: #d4edda; color: #155724; }
.co-badge.primary { background: #cce5ff; color: #004085; }
.co-badge.teal { background: #d1ecf1; color: #0c5460; }
.co-badge.danger { background: #f8d7da; color: #721c24; }
.co-payment-section { background: #f8f9fa; border-radius: 10px; padding: 0.75rem; margin-bottom: 0.5rem; }
.co-payment-section img { max-width: 120px; border-radius: 8px; border: 1px solid #ddd; cursor: pointer; }
.co-actions-form select, .co-actions-form textarea {
    font-size: 0.8rem; padding: 0.35rem 0.5rem; border-radius: 8px; border: 1px solid #ddd; width: 100%; margin-bottom: 0.4rem;
}
.co-actions-form textarea { resize: vertical; min-height: 40px; }
.co-actions-form .btn-sm { font-size: 0.76rem; padding: 0.3rem 0.7rem; border-radius: 8px; }
.co-verify-btns { display: flex; gap: 0.4rem; margin-top: 0.5rem; }
.co-verify-btns button { flex: 1; font-size: 0.72rem; padding: 0.3rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
.btn-verify { background: #d4edda; color: #155724; }
.btn-verify:hover { background: #c3e6cb; }
.btn-reject { background: #f8d7da; color: #721c24; }
.btn-reject:hover { background: #f5c6cb; }

/* Modal */
.co-modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center; }
.co-modal-overlay.show { display:flex; }
.co-modal-content { background:#fff; border-radius:16px; max-width:500px; width:90%; max-height:90vh; overflow-y:auto; padding:1.5rem; position:relative; }
.co-modal-close { position:absolute; top:0.75rem; right:0.75rem; border:none; background:#f0f0f0; width:32px; height:32px; border-radius:50%; cursor:pointer; font-size:1rem; display:flex; align-items:center; justify-content:center; }
.co-modal-img { width:100%; border-radius:10px; }

@media (max-width:992px) {
    .co-order-grid { grid-template-columns: 90px 1fr; }
    .co-payment-section, .co-actions-form { grid-column: 1 / -1; }
}
</style>

<?php include '../includes/admin-sidebar.php'; ?>

<div class="admin-custom-orders">
    <div class="mb-4">
        <h1 style="font-size:2rem; font-weight:800;">
            <i class="fas fa-shirt"></i> Custom Orders
        </h1>
        <p class="text-muted">Manage customer custom apparel orders, verify payments, and update order status</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="co-stats-row">
        <div class="co-stat-card">
            <div class="stat-num"><?php echo $totalOrders; ?></div>
            <div class="stat-text">Total Orders</div>
        </div>
        <div class="co-stat-card">
            <div class="stat-num" style="color:#e67e22;"><?php echo $pendingPayment; ?></div>
            <div class="stat-text">Pending Payment</div>
        </div>
        <div class="co-stat-card">
            <div class="stat-num" style="color:#8e44ad;"><?php echo $processingOrders; ?></div>
            <div class="stat-text">Processing</div>
        </div>
        <div class="co-stat-card">
            <div class="stat-num" style="color:#27ae60;"><?php echo $completedOrders; ?></div>
            <div class="stat-text">Completed</div>
        </div>
        <div class="co-stat-card">
            <div class="stat-num" style="color:#2d6a4f;">₱<?php echo number_format($totalRevenue, 0); ?></div>
            <div class="stat-text">Revenue</div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="co-filter-tabs">
        <a href="custom-orders.php" class="co-filter-tab <?php echo !$statusFilter ? 'active' : ''; ?>">All</a>
        <a href="?status=pending_payment" class="co-filter-tab <?php echo $statusFilter === 'pending_payment' ? 'active' : ''; ?>">Pending Payment</a>
        <a href="?status=payment_uploaded" class="co-filter-tab <?php echo $statusFilter === 'payment_uploaded' ? 'active' : ''; ?>">Payment Uploaded</a>
        <a href="?status=payment_verified" class="co-filter-tab <?php echo $statusFilter === 'payment_verified' ? 'active' : ''; ?>">Verified</a>
        <a href="?status=processing" class="co-filter-tab <?php echo $statusFilter === 'processing' ? 'active' : ''; ?>">Processing</a>
        <a href="?status=printing" class="co-filter-tab <?php echo $statusFilter === 'printing' ? 'active' : ''; ?>">Printing</a>
        <a href="?status=ready_pickup" class="co-filter-tab <?php echo $statusFilter === 'ready_pickup' ? 'active' : ''; ?>">Ready</a>
        <a href="?status=delivered" class="co-filter-tab <?php echo $statusFilter === 'delivered' ? 'active' : ''; ?>">Delivered</a>
        <a href="?status=cancelled" class="co-filter-tab <?php echo $statusFilter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
    </div>

    <!-- Orders List -->
    <?php if ($orders && $orders->num_rows > 0): ?>
        <?php while ($o = $orders->fetch_assoc()): 
            $statusInfo = $statusLabels[$o['status']] ?? ['label' => $o['status'], 'badge' => 'secondary'];
        ?>
            <div class="co-order-card">
                <div class="co-order-grid">
                    <!-- Thumbnail -->
                    <div>
                        <img src="../<?php echo htmlspecialchars($o['design_image']); ?>" 
                             class="co-thumb" alt="Design"
                             onclick="showModal('../<?php echo htmlspecialchars($o['design_image']); ?>')"
                             onerror="this.src='https://placehold.co/90x90/f0f0f0/999?text=Design'">
                    </div>

                    <!-- Order Info -->
                    <div class="co-info">
                        <h6>
                            Order #<?php echo (int)$o['id']; ?> — Custom <?php echo htmlspecialchars($typeNames[$o['product_type']] ?? 'Apparel'); ?>
                            <span class="co-badge <?php echo $statusInfo['badge']; ?>"><?php echo $statusInfo['label']; ?></span>
                        </h6>
                        <p><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($o['fullname']); ?> (<?php echo htmlspecialchars($o['email']); ?>)</p>
                        <p><i class="fas fa-ruler me-1"></i>Size: <?php echo htmlspecialchars($o['size']); ?> · Qty: <?php echo (int)$o['quantity']; ?> · Color: <?php echo htmlspecialchars($o['apparel_color']); ?></p>
                        <p><i class="fas fa-calendar me-1"></i><?php echo date('M d, Y h:i A', strtotime($o['created_at'])); ?></p>
                        <p><strong style="color:var(--accent-green,#2d6a4f); font-size:1rem;">₱<?php echo number_format($o['total_price'], 2); ?></strong>
                        <?php if ($o['discount_amount'] > 0): ?>
                            <small class="text-success">(<?php echo ucfirst($o['discount_type']); ?> -₱<?php echo number_format($o['discount_amount'], 2); ?>)</small>
                        <?php endif; ?>
                        </p>
                        <?php if ($o['notes']): ?>
                            <p><i class="fas fa-sticky-note me-1"></i><?php echo htmlspecialchars($o['notes']); ?></p>
                        <?php endif; ?>
                        <?php if ($o['admin_notes']): ?>
                            <p style="color:#2d6a4f;"><i class="fas fa-comment me-1"></i><strong>Admin:</strong> <?php echo htmlspecialchars($o['admin_notes']); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Payment Info -->
                    <div>
                        <?php if ($o['payment_method']): ?>
                            <div class="co-payment-section">
                                <p style="font-size:0.78rem; margin:0 0 0.3rem; font-weight:700;"><?php echo strtoupper(htmlspecialchars($o['payment_method'])); ?></p>
                                <span class="co-badge <?php echo $o['payment_status'] === 'verified' ? 'success' : ($o['payment_status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                    <?php echo htmlspecialchars($o['payment_status']); ?>
                                </span>
                                <?php if ($o['reference_number']): ?>
                                    <p style="font-size:0.72rem; margin:0.3rem 0 0; color:#666;">Ref: <?php echo htmlspecialchars($o['reference_number']); ?></p>
                                <?php endif; ?>
                                <?php if ($o['payment_proof']): ?>
                                    <div style="margin-top:0.4rem;">
                                        <img src="../<?php echo htmlspecialchars($o['payment_proof']); ?>" 
                                             alt="Payment Proof" 
                                             onclick="showModal('../<?php echo htmlspecialchars($o['payment_proof']); ?>')"
                                             onerror="this.style.display='none'">
                                    </div>
                                <?php endif; ?>
                                <?php if ($o['payment_status'] === 'pending' && $o['payment_id']): ?>
                                    <div class="co-verify-btns">
                                        <form method="POST" style="flex:1;">
                                            <input type="hidden" name="payment_id" value="<?php echo (int)$o['payment_id']; ?>">
                                            <button type="submit" name="verify_payment" value="verify" class="btn-verify" style="width:100%;">
                                                <i class="fas fa-check me-1"></i>Verify
                                            </button>
                                        </form>
                                        <form method="POST" style="flex:1;">
                                            <input type="hidden" name="payment_id" value="<?php echo (int)$o['payment_id']; ?>">
                                            <button type="submit" name="verify_payment" value="reject" class="btn-reject" style="width:100%;">
                                                <i class="fas fa-times me-1"></i>Reject
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p style="color:#bbb; font-size:0.8rem; text-align:center;"><i class="fas fa-clock me-1"></i>No payment yet</p>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div>
                        <form method="POST" class="co-actions-form">
                            <input type="hidden" name="order_id" value="<?php echo (int)$o['id']; ?>">
                            <select name="status" class="form-select form-select-sm">
                                <?php foreach ($statusLabels as $key => $val): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $o['status'] === $key ? 'selected' : ''; ?>><?php echo $val['label']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <textarea name="admin_notes" placeholder="Admin notes..."><?php echo htmlspecialchars($o['admin_notes'] ?? ''); ?></textarea>
                            <button type="submit" class="btn btn-sm btn-primary w-100" style="border-radius:8px;">
                                <i class="fas fa-save me-1"></i>Update
                            </button>
                        </form>
                        <div style="margin-top:0.4rem;">
                            <a href="../<?php echo htmlspecialchars($o['design_image']); ?>" 
                               download="order_<?php echo (int)$o['id']; ?>_design.png" 
                               class="btn btn-sm btn-outline-secondary w-100" style="border-radius:8px; font-size:0.76rem;">
                                <i class="fas fa-download me-1"></i>Download Design
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="co-order-card" style="text-align:center; padding:3rem;">
            <i class="fas fa-shirt" style="font-size:3rem; color:#ddd; margin-bottom:1rem; display:block;"></i>
            <h5 class="text-muted">No custom orders yet</h5>
            <p class="text-muted">Customer custom apparel orders will appear here.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Image Modal -->
<div class="co-modal-overlay" id="coModal">
    <div class="co-modal-content">
        <button class="co-modal-close" onclick="closeModal()">&times;</button>
        <img id="coModalImg" class="co-modal-img" src="" alt="Preview">
    </div>
</div>

<script>
function showModal(src) {
    document.getElementById('coModalImg').src = src;
    document.getElementById('coModal').classList.add('show');
}
function closeModal() {
    document.getElementById('coModal').classList.remove('show');
}
document.getElementById('coModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->
<script src="../js/admin-sidebar.js"></script>

<?php include '../includes/footer/footer.php'; ?>
