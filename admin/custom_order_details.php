<?php
require '../includes/config.php';
require_once '../includes/paymongo.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Custom Order Details';

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare(
    "SELECT co.*, u.fullname, u.email, u.phone, u.street_address, u.barangay, u.city, u.province, u.zipcode
       FROM custom_orders co JOIN users u ON co.user_id = u.id
      WHERE co.id = ?"
);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: custom-orders.php");
    exit();
}

$address = implode(', ', array_filter([
    $order['street_address'] ?? '', $order['barangay'] ?? '', $order['city'] ?? '',
    $order['province'] ?? '', $order['zipcode'] ?? '',
]));

$payStmt = $conn->prepare("SELECT * FROM custom_order_payments WHERE custom_order_id = ? ORDER BY created_at DESC LIMIT 1");
$payStmt->bind_param("i", $order_id);
$payStmt->execute();
$payment = $payStmt->get_result()->fetch_assoc();
$payStmt->close();

// Latest PayMongo session, for the gateway's own payment id and channel.
$gateway = null;
if (paymongoTableExists()) {
    $gStmt = $conn->prepare("SELECT * FROM paymongo_sessions WHERE order_kind = 'custom' AND order_id = ? ORDER BY id DESC LIMIT 1");
    $gStmt->bind_param("i", $order_id);
    $gStmt->execute();
    $gateway = $gStmt->get_result()->fetch_assoc();
    $gStmt->close();
}

$statusLabels = [
    'pending_payment'  => 'Pending Payment',
    'payment_uploaded' => 'Payment Uploaded',
    'payment_verified' => 'Payment Verified',
    'processing'       => 'Processing',
    'printing'         => 'Printing',
    'ready_pickup'     => 'Ready for Pickup',
    'delivered'        => 'Delivered',
    'cancelled'        => 'Cancelled',
];
$typeNames = ['tshirt' => 'T-Shirt', 'hoodie' => 'Hoodie', 'polo' => 'Polo'];
$typeName  = $typeNames[$order['product_type']] ?? 'Custom Apparel';
$qty       = (int)$order['quantity'];
$lines     = [
    $typeName . ' Base' => (float)$order['base_price'],
    'Print Cost'        => (float)$order['print_cost'],
    'Color Cost'        => (float)$order['color_cost'],
];
?>

<?php include '../includes/header/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="admin-container" data-live="co-detail">
    <div class="mb-4">
        <h1 class="text-coffee-dark mb-2" style="font-size: 2rem; font-weight: 800;">
            <i class="fas fa-shirt"></i> Custom Order #<?php echo (int)$order['id']; ?>
        </h1>
        <span class="badge bg-secondary"><?php echo htmlspecialchars($statusLabels[$order['status']] ?? $order['status']); ?></span>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

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
                        <p style="color: var(--coffee-dark); font-weight: 600;"><?php echo date('F d, Y h:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                </div>
                <?php if ($address !== ''): ?>
                <p class="text-muted mb-1">Address</p>
                <div style="background-color: #f8f7f4; padding: 1rem; border-radius: 8px;">
                    <p class="mb-0"><?php echo htmlspecialchars($address); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Design -->
            <div class="admin-card mb-4">
                <h5 class="text-coffee-dark mb-4" style="font-weight: 700;">Design</h5>
                <div class="row">
                    <div class="col-md-5 mb-3">
                        <a href="../<?php echo htmlspecialchars($order['design_image']); ?>" target="_blank">
                            <img src="../<?php echo htmlspecialchars($order['design_image']); ?>" alt="Design"
                                 style="width:100%; border:1px solid #ddd; border-radius:8px; background:#f8f9fa; cursor:zoom-in;"
                                 onerror="this.onerror=null;this.src='https://placehold.co/300x300/f0f0f0/999?text=Design'">
                        </a>
                        <a href="../<?php echo htmlspecialchars($order['design_image']); ?>"
                           download="order_<?php echo (int)$order['id']; ?>_design.png"
                           class="btn btn-sm btn-outline-secondary w-100 mt-2">
                            <i class="fas fa-download"></i> Download Design
                        </a>
                    </div>
                    <div class="col-md-7">
                        <table class="table">
                            <tr><td class="text-muted">Apparel</td><td><strong><?php echo htmlspecialchars($typeName); ?></strong></td></tr>
                            <tr><td class="text-muted">Color</td><td>
                                <span style="display:inline-block; width:16px; height:16px; border-radius:4px; border:1px solid #ccc; vertical-align:middle; background:<?php echo htmlspecialchars($order['apparel_color']); ?>;"></span>
                                <?php echo htmlspecialchars($order['apparel_color']); ?>
                            </td></tr>
                            <tr><td class="text-muted">Size</td><td><?php echo htmlspecialchars($order['size']); ?></td></tr>
                            <tr><td class="text-muted">Quantity</td><td><?php echo $qty; ?></td></tr>
                        </table>
                    </div>
                </div>
                <?php if ($order['notes']): ?>
                <div class="mt-2">
                    <h6 class="text-coffee-dark mb-2" style="font-weight: 700;">Customer Notes</h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Cost Breakdown -->
            <div class="admin-card mb-4">
                <h5 class="text-coffee-dark mb-4" style="font-weight: 700;">Cost Breakdown</h5>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th style="text-align: right;">Unit Price</th>
                                <th style="text-align: right;">Qty</th>
                                <th style="text-align: right;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lines as $label => $unit): if ($unit <= 0) continue; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($label); ?></td>
                                <td style="text-align: right;">₱<?php echo number_format($unit, 2); ?></td>
                                <td style="text-align: right;"><?php echo $qty; ?></td>
                                <td style="text-align: right; font-weight: 700;">₱<?php echo number_format($unit * $qty, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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
                        <span class="text-muted">Discount (<?php echo strtoupper(htmlspecialchars($order['discount_type'])); ?>):</span>
                        <span style="color: var(--accent-green); font-weight: 700;">-₱<?php echo number_format($order['discount_amount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 1.1rem; color: var(--coffee-dark);">
                        <span>Total:</span>
                        <span>₱<?php echo number_format($order['total_price'], 2); ?></span>
                    </div>
                </div>
                <a href="../custom-invoice.php?order_id=<?php echo (int)$order['id']; ?>" class="btn btn-outline-secondary w-100 mt-3">
                    <i class="fas fa-file-invoice"></i> Download Invoice
                </a>
            </div>

            <!-- Payment Information -->
            <div class="admin-card mb-4">
                <h5 class="text-coffee-dark mb-3" style="font-weight: 700;">Payment Information</h5>
                <?php if ($payment): ?>
                    <p class="text-muted mb-2">Payment Method</p>
                    <p style="color: var(--coffee-dark); font-weight: 600;">
                        <i class="fas fa-<?php echo $payment['payment_method'] === 'cod' ? 'money-bill' : 'mobile-alt'; ?>"></i>
                        <?php echo htmlspecialchars(paymentMethodLabel($payment['payment_method'])); ?>
                        <?php if ($gateway && $gateway['payment_method_used']): ?>
                            <small class="text-muted">(<?php echo htmlspecialchars(strtoupper($gateway['payment_method_used'])); ?>)</small>
                        <?php endif; ?>
                    </p>
                    <p class="text-muted mb-2">Status</p>
                    <p>
                        <span class="badge bg-<?php echo $payment['payment_status'] === 'verified' ? 'success' : ($payment['payment_status'] === 'rejected' ? 'danger' : 'warning text-dark'); ?>">
                            <?php echo htmlspecialchars(ucfirst($payment['payment_status'])); ?>
                        </span>
                    </p>
                    <?php if ($payment['reference_number']): ?>
                    <p class="text-muted mb-2">Reference Number</p>
                    <p style="color: var(--coffee-dark); font-weight: 600; word-break: break-all;"><?php echo htmlspecialchars($payment['reference_number']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($payment['payment_proof'])): ?>
                    <p class="text-muted mb-2 mt-3">Payment Screenshot</p>
                    <a href="../<?php echo htmlspecialchars($payment['payment_proof']); ?>" target="_blank">
                        <img src="../<?php echo htmlspecialchars($payment['payment_proof']); ?>" alt="Payment Proof"
                             style="max-width:100%; max-height:300px; border:1px solid #ddd; border-radius:8px; cursor:zoom-in;">
                    </a>
                    <?php endif; ?>
                    <?php if ($payment['payment_status'] === 'pending'): ?>
                    <div class="d-flex gap-2 mt-3">
                        <?php foreach (['verify' => 'success', 'reject' => 'danger'] as $act => $color): ?>
                        <form method="POST" action="custom-orders.php" class="flex-fill"><?php echo csrfTokenField(); ?>
                            <input type="hidden" name="payment_id" value="<?php echo (int)$payment['id']; ?>">
                            <input type="hidden" name="back_to_detail" value="<?php echo (int)$order['id']; ?>">
                            <button type="submit" name="verify_payment" value="<?php echo $act; ?>" class="btn btn-sm btn-outline-<?php echo $color; ?> w-100">
                                <?php echo ucfirst($act); ?>
                            </button>
                        </form>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted mb-0"><i class="fas fa-clock"></i> No payment yet</p>
                <?php endif; ?>
            </div>

            <!-- Order Status -->
            <div class="admin-card">
                <h5 class="text-coffee-dark mb-3" style="font-weight: 700;">Order Status</h5>
                <form method="POST" action="custom-orders.php" data-ajax>
                    <?php echo csrfTokenField(); ?>
                    <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                    <input type="hidden" name="back_to_detail" value="<?php echo (int)$order['id']; ?>">
                    <select name="status" class="form-control mb-2">
                        <?php foreach ($statusLabels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $order['status'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <textarea name="admin_notes" class="form-control mb-2" rows="3" placeholder="Admin notes..."><?php echo htmlspecialchars($order['admin_notes'] ?? ''); ?></textarea>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <a href="custom-orders.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left"></i> Back to Custom Orders
        </a>
    </div>
</div>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->
<script src="../js/admin-sidebar.js"></script>

<?php include '../includes/footer/footer.php'; ?>
