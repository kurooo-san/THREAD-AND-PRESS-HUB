<?php
require 'includes/config.php';
redirectToLogin();

$pageTitle = 'Order Confirmation';

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id === 0) {
    header("Location: orders.php");
    exit();
}

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order_result = $stmt->get_result();

if ($order_result->num_rows === 0) {
    header("Location: orders.php");
    exit();
}

$order = $order_result->fetch_assoc();
$stmt->close();

// Clear cart
$_SESSION['cart'] = array();

// One-shot success animation — only right after checkout, never on a revisit.
require_once 'includes/payment-success.php';
$successFlash = takePaymentSuccessFlash($order_id);
?>

<?php include 'includes/header/header.php'; ?>

<?php
if ($successFlash !== null) {
    // PayMongo has already collected the money, so that ending must not be
    // told to "prepare the exact amount" like a COD order.
    $paidOnline = ($successFlash['kind'] ?? '') === 'order_paymongo';
    renderPaymentSuccess([
        'title'   => $paidOnline ? 'Payment Successful!' : 'Order Confirmed!',
        'message' => $paidOnline
            ? 'Your payment went through and your order is confirmed. A receipt has been sent to your email.'
            : 'Cash on Delivery — please prepare the exact amount when your parcel arrives.',
        'badge'   => 'Order #' . $order['id'],
        'amount'  => $order['total'] ?? null,
    ]);
}
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card" style="border: 1px solid var(--border-light); border-radius: var(--radius-md);">
                <div class="card-body text-center py-5">
                    <div style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    
                    <h2 class="mb-2" style="font-weight: 800;">Order Confirmed!</h2>
                    <p class="text-muted mb-4">Thank you for your order. Your items will be on their way soon!</p>

                    <div class="mb-4" style="background: var(--bg-light); border-radius: var(--radius-sm); padding: 1rem;">
                        <h5 class="mb-1">Order Number</h5>
                        <h3 style="font-weight: 800;">#<?php echo htmlspecialchars($order['id']); ?></h3>
                    </div>

                    <div class="text-start">
                        <h5 class="mb-3" style="font-weight: 700;">Order Details</h5>
                        
                        <div class="mb-3">
                            <h6 class="text-muted">Items Ordered</h6>
                            <?php
                            $items_stmt = $conn->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
                            $items_stmt->bind_param("i", $order_id);
                            $items_stmt->execute();
                            $items_result = $items_stmt->get_result();
                            
                            while ($item = $items_result->fetch_assoc()):
                            ?>
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0;">
                                    <div>
                                        <strong><?php echo htmlspecialchars($item['name']); ?></strong> x<?php echo $item['quantity']; ?><br>
                                        <small class="text-muted">
                                            <?php if (!empty($item['color'])) echo 'Color: ' . htmlspecialchars($item['color']); ?>
                                            <?php if (!empty($item['size'])) echo ' | Size: ' . htmlspecialchars($item['size']); ?>
                                        </small>
                                    </div>
                                    <span>₱<?php echo number_format($item['subtotal'], 2); ?></span>
                                </div>
                            <?php endwhile;
                            $items_stmt->close();
                            ?>
                        </div>

                        <hr>

                        <div class="mb-3">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span class="text-muted">Subtotal:</span>
                                <span>₱<?php echo number_format($order['subtotal'], 2); ?></span>
                            </div>
                            <?php if ($order['discount_amount'] > 0): ?>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: var(--accent-green);">
                                <span class="text-muted">Discount (<?php echo strtoupper($order['discount_type']); ?>):</span>
                                <span>-₱<?php echo number_format($order['discount_amount'], 2); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php
                            // VAT shown as the reconciling difference so the visible
                            // breakdown always sums exactly to the stored total
                            // (also correct for old orders placed before VAT: 0 → hidden).
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
                            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                                <span class="text-muted">Delivery Fee:</span>
                                <span>₱<?php echo number_format($order['delivery_fee'], 2); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 1.2rem; color: var(--coffee-dark); border-top: 2px solid #eee; padding-top: 1rem;">
                                <span>Total:</span>
                                <span>₱<?php echo number_format($order['total'], 2); ?></span>
                            </div>
                        </div>

                        <hr>

                        <div class="mb-3">
                            <h6 class="text-muted">Payment Method</h6>
                            <p style="color: var(--coffee-dark); font-weight: 600;">
                                <i class="fas fa-<?php echo $order['payment_method'] === 'cod' ? 'money-bill' : 'mobile-alt'; ?>"></i>
                                <?php 
                                    echo htmlspecialchars(paymentMethodLabel($order['payment_method']));
                                ?>
                            </p>
                        </div>

                        <div class="mb-3">
                            <h6 class="text-muted">Delivery Address</h6>
                            <p style="color: var(--coffee-dark);"><?php echo htmlspecialchars($order['delivery_address']); ?></p>
                        </div>

                        <?php if ($order['notes']): ?>
                        <div class="mb-3">
                            <h6 class="text-muted">Special Instructions</h6>
                            <p style="color: var(--coffee-dark);"><?php echo htmlspecialchars($order['notes']); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php
                        // Show linked custom designs
                        $cdCheck = $conn->query("SHOW TABLES LIKE 'custom_designs'");
                        if ($cdCheck && $cdCheck->num_rows > 0):
                            $designStmt = $conn->prepare("SELECT * FROM custom_designs WHERE order_id = ?");
                            $designStmt->bind_param("i", $order_id);
                            $designStmt->execute();
                            $designResults = $designStmt->get_result();
                            if ($designResults->num_rows > 0):
                        ?>
                        <div class="mb-3">
                            <h6 class="text-muted"><i class="fas fa-palette me-1"></i> Custom Designs</h6>
                            <?php while ($cd = $designResults->fetch_assoc()): ?>
                            <div style="display:flex; gap:1rem; align-items:center; padding:0.75rem; background:var(--bg-light); border-radius:10px; margin-bottom:0.5rem;">
                                <img src="<?php echo htmlspecialchars($cd['design_image']); ?>" style="width:60px; height:60px; object-fit:cover; border-radius:8px; border:1px solid #eee;" alt="Design">
                                <div>
                                    <strong><?php echo ucfirst(htmlspecialchars($cd['product_type'])); ?> Design</strong><br>
                                    <small class="text-muted">Status: <?php echo ucfirst(htmlspecialchars($cd['status'])); ?></small>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <?php 
                            endif;
                            $designStmt->close();
                        endif;
                        ?>

                        <hr>

                        <div class="mb-4" style="background: var(--bg-light); border-radius: var(--radius-sm); padding: 1rem;">
                            <small>
                                <i class="fas fa-info-circle" style="margin-right: 0.25rem;"></i>
                                <?php if ($order['payment_method'] === 'cod'): ?>
                                    Your order has been confirmed! Please have the exact payment amount ready for our delivery rider.
                                <?php else: ?>
                                    Your order has been received! Our team will verify your payment and you will receive an email confirmation shortly.
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>

                    <div class="mt-4">
                        <a href="orders.php" class="btn btn-dark me-2" style="border-radius: var(--radius-sm); padding: 0.5rem 1.5rem;">
                            <i class="fas fa-history" style="margin-right: 0.5rem;"></i> View All Orders
                        </a>
                        <a href="invoice.php?order_id=<?php echo (int)$order['id']; ?>" class="btn btn-outline-secondary me-2" target="_blank" style="border-radius: var(--radius-sm); padding: 0.5rem 1.5rem;">
                            <i class="fas fa-file-pdf" style="margin-right: 0.5rem;"></i> Download Invoice
                        </a>
                        <a href="shop.php" class="btn btn-outline-dark" style="border-radius: var(--radius-sm); padding: 0.5rem 1.5rem;">
                            <i class="fas fa-shopping-bag" style="margin-right: 0.5rem;"></i> Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Clear only what this order used. A Buy Now order must NOT wipe the basket
// the customer still has waiting.
<?php $lastMode = $_SESSION['last_order_mode'] ?? 'cart'; unset($_SESSION['last_order_mode']); ?>
if (<?php echo json_encode($lastMode); ?> === 'buynow') {
    localStorage.removeItem('buyNow');
    localStorage.removeItem('buyNowSubtotal');
} else {
    // Remove ONLY the lines that were part of this order. Items the customer
    // left unticked in the cart must still be there afterwards.
    try {
        var ordered = JSON.parse(localStorage.getItem('cartSelection')) || [];
        var cart    = JSON.parse(localStorage.getItem('cart')) || [];
        if (ordered.length && cart.length) {
            var key = function (i) { return [i.id, i.color || '', i.size || ''].join('|'); };
            var done = ordered.map(key);
            var left = cart.filter(function (i) { return done.indexOf(key(i)) === -1; });
            if (left.length) { localStorage.setItem('cart', JSON.stringify(left)); }
            else { localStorage.removeItem('cart'); }
        } else {
            localStorage.removeItem('cart');
        }
    } catch (e) {
        localStorage.removeItem('cart');
    }
    localStorage.removeItem('cartSelection');
    localStorage.removeItem('cartDeselected');
    localStorage.removeItem('subtotal');
    localStorage.removeItem('total');
}
// Reset cart badge in header
var navBadge = document.getElementById('navCartCount');
if (navBadge) { navBadge.style.display = 'none'; navBadge.textContent = '0'; }
</script>

<?php include 'includes/footer/footer.php'; ?>
