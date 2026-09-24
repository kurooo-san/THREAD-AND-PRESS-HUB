<?php
require 'includes/config.php';
redirectToLogin();

$pageTitle = 'Custom Order Summary';

// Run migration if tables don't exist
$tableCheck = $conn->query("SHOW TABLES LIKE 'custom_orders'");
if ($tableCheck->num_rows === 0) {
    $migrationSQL = file_get_contents(__DIR__ . '/migrate_custom_orders.sql');
    if ($migrationSQL) {
        $conn->multi_query($migrationSQL);
        while ($conn->next_result()) {;}
    }
}

// Get design info
$designId = intval($_GET['design_id'] ?? 0);
if ($designId <= 0) {
    header("Location: custom-design.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM custom_designs WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $designId, $_SESSION['user_id']);
$stmt->execute();
$design = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$design) {
    header("Location: custom-design.php");
    exit();
}

// Get parameters from URL
// Validate against every configured apparel type, not just three of them.
require_once __DIR__ . '/includes/apparel-config.php';
$validApparelTypes = getApparelTypeKeys();
$apparelType = in_array($_GET['type'] ?? '', $validApparelTypes, true)
    ? $_GET['type']
    : (in_array($design['product_type'], $validApparelTypes, true) ? $design['product_type'] : 'tshirt');
$apparelColor = preg_match('/^#[0-9A-Fa-f]{6}$/', $_GET['color'] ?? '') ? $_GET['color'] : '#FFFFFF';
$size = in_array($_GET['size'] ?? '', ['XS', 'S', 'M', 'L', 'XL', '2XL']) ? $_GET['size'] : 'M';
$quantity = max(1, min(100, intval($_GET['qty'] ?? 1)));
$printSize = in_array($_GET['print_size'] ?? '', ['small', 'medium', 'large', 'full']) ? $_GET['print_size'] : 'medium';
$discountType = in_array($_GET['discount'] ?? '', ['regular', 'senior', 'pwd']) ? $_GET['discount'] : 'regular';

// Validate discount type matches user account type
$user_type_stmt = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
$user_type_stmt->bind_param("i", $_SESSION['user_id']);
$user_type_stmt->execute();
$user_type_result = $user_type_stmt->get_result()->fetch_assoc();
$user_type_stmt->close();
$actual_user_type = $user_type_result['user_type'] ?? 'regular';

if ($discountType !== 'regular' && $discountType !== $actual_user_type) {
    $discountType = 'regular';
}

// Pricing — worked out by the shared helper so this page and the estimate on
// the "My Designs" card always agree. It also prices apparel types beyond
// t-shirt/hoodie/polo properly instead of falling back to a placeholder.
require_once __DIR__ . '/includes/apparel-config.php';

$designData = json_decode($design['design_data'] ?? '{}', true);
$colorsUsed = max(1, intval($designData['colorsUsed'] ?? 1));

$price = customDesignPrice($apparelType, $printSize, $quantity, $colorsUsed, $discountType);

$basePrice       = $price['base'];
$printCost       = $price['print'];
$colorCost       = $price['color'];
$unitPrice       = $price['unit'];
$subtotal        = $price['subtotal'];
$discountPercent = $price['discountRate'];
$discountAmount  = $price['discount'];
$totalPrice      = $price['total'];

$typeNames = ['tshirt' => 'T-Shirt', 'hoodie' => 'Hoodie', 'polo' => 'Polo'];
$typeName = $typeNames[$apparelType] ?? 'T-Shirt';
$printSizeNames = ['small' => 'Small (4×4")', 'medium' => 'Medium (8×8")', 'large' => 'Large (12×12")', 'full' => 'Full Print'];

// Handle order creation
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $stmt = $conn->prepare("INSERT INTO custom_orders (user_id, design_id, design_image, product_type, apparel_color, size, quantity, base_price, print_cost, color_cost, subtotal, discount_type, discount_amount, total_price, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_payment')");
    $notes = trim($_POST['notes'] ?? $design['notes'] ?? '');
    $stmt->bind_param("iissssiddddsdds",
        $_SESSION['user_id'],
        $designId,
        $design['design_image'],
        $apparelType,
        $apparelColor,
        $size,
        $quantity,
        $basePrice,
        $printCost,
        $colorCost,
        $subtotal,
        $discountType,
        $discountAmount,
        $totalPrice,
        $notes
    );

    if ($stmt->execute()) {
        $orderId = $conn->insert_id;
        // Update design status to approved (order placed)
        $conn->query("UPDATE custom_designs SET status = 'approved', order_id = NULL WHERE id = " . intval($designId));
        header("Location: custom-payment.php?order_id=" . $orderId);
        exit();
    } else {
        $error = 'Failed to create order. Please try again.';
    }
    $stmt->close();
}
?>

<?php include 'includes/header/header.php'; ?>

<style>
.order-summary-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 1rem;
}
.order-summary-header {
    text-align: center;
    margin-bottom: 2rem;
}
.order-summary-header h1 {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-dark, #1a1a1a);
}
.order-summary-header p {
    color: var(--text-light, #666);
}
.summary-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid var(--border-light, #e5e5e5);
    box-shadow: 0 2px 16px rgba(0,0,0,0.06);
    overflow: hidden;
    margin-bottom: 1.5rem;
}
.summary-card-header {
    padding: 1rem 1.5rem;
    background: #fafafa;
    border-bottom: 1px solid #eee;
    font-weight: 700;
    font-size: 1rem;
}
.summary-card-body {
    padding: 1.5rem;
}
.design-preview-row {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 1.5rem;
    align-items: start;
}
.design-preview-img {
    width: 100%;
    border-radius: 12px;
    border: 1px solid #eee;
    background: #f8f9fa;
}
.design-details-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.design-details-list li {
    display: flex;
    justify-content: space-between;
    padding: 0.6rem 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.9rem;
}
.design-details-list li:last-child {
    border-bottom: none;
}
.design-details-list .label {
    color: #666;
}
.design-details-list .value {
    font-weight: 600;
    color: #333;
}
.price-breakdown {
    list-style: none;
    padding: 0;
    margin: 0;
}
.price-breakdown li {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    font-size: 0.9rem;
    color: #555;
}
.price-breakdown li.discount {
    color: #27ae60;
}
.price-breakdown li.total-row {
    border-top: 2px solid var(--accent-green, #2d6a4f);
    margin-top: 0.5rem;
    padding-top: 0.75rem;
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--accent-green, #2d6a4f);
}
.color-swatch {
    display: inline-block;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid #ddd;
    vertical-align: middle;
    margin-right: 0.3rem;
}
.btn-proceed {
    display: block;
    width: 100%;
    padding: 1rem;
    /* Explicit green: var(--accent-green) is aliased to near-black in
       style.css, so the #2d6a4f fallback was never used. */
    background: #2d6a4f;
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-proceed:hover {
    background: #245a42;
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(45,106,79,0.3);
}
.btn-back {
    display: block;
    width: 100%;
    padding: 0.75rem;
    background: #fff;
    color: #666;
    border: 1px solid #ddd;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    transition: all 0.2s;
    margin-top: 0.75rem;
}
.btn-back:hover {
    border-color: #aaa;
    color: #333;
}
.order-steps {
    display: flex;
    justify-content: center;
    gap: 0;
    margin-bottom: 2rem;
}
.order-step {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.82rem;
    color: #bbb;
    font-weight: 600;
}
.order-step.active {
    color: var(--accent-green, #2d6a4f);
}
.order-step.completed {
    color: #27ae60;
}
.order-step .step-num {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    background: #eee;
    color: #999;
}
.order-step.active .step-num {
    background: var(--accent-green, #2d6a4f);
    color: #fff;
}
.order-step.completed .step-num {
    background: #27ae60;
    color: #fff;
}
.step-connector {
    width: 40px;
    height: 2px;
    background: #eee;
    margin: 0 0.25rem;
}
.step-connector.completed {
    background: #27ae60;
}
@media (max-width: 768px) {
    .design-preview-row {
        grid-template-columns: 1fr;
    }
    .design-preview-img {
        max-width: 250px;
        margin: 0 auto;
    }
    .order-steps {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .step-connector {
        display: none;
    }
}
</style>

<div class="order-summary-container">
    <!-- Progress Steps -->
    <div class="order-steps">
        <div class="order-step completed">
            <span class="step-num"><i class="fas fa-check"></i></span>
            Design
        </div>
        <div class="step-connector completed"></div>
        <div class="order-step active">
            <span class="step-num">2</span>
            Order Summary
        </div>
        <div class="step-connector"></div>
        <div class="order-step">
            <span class="step-num">3</span>
            Payment
        </div>
        <div class="step-connector"></div>
        <div class="order-step">
            <span class="step-num">4</span>
            Tracking
        </div>
    </div>

    <div class="order-summary-header">
        <h1><i class="fas fa-clipboard-list me-2"></i>Custom Order Summary</h1>
        <p>Review your custom apparel order before proceeding to payment</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Design Preview -->
    <div class="summary-card">
        <div class="summary-card-header">
            <i class="fas fa-shirt me-2"></i>Design Preview
        </div>
        <div class="summary-card-body">
            <div class="design-preview-row">
                <div>
                    <div style="text-align:center; margin-bottom:0.3rem; font-weight:600; font-size:0.8rem; color:#888;">FRONT</div>
                    <img src="<?php echo htmlspecialchars($design['design_image']); ?>" class="design-preview-img" alt="Your Design (Front)" onerror="this.src='https://placehold.co/300x400/f0f0f0/999?text=Design'">
                </div>
                <?php if (!empty($design['design_image_back'])): ?>
                <div>
                    <div style="text-align:center; margin-bottom:0.3rem; font-weight:600; font-size:0.8rem; color:#888;">BACK</div>
                    <img src="<?php echo htmlspecialchars($design['design_image_back']); ?>" class="design-preview-img" alt="Your Design (Back)" onerror="this.src='https://placehold.co/300x400/f0f0f0/999?text=Back'">
                </div>
                <?php endif; ?>
                <div>
                    <ul class="design-details-list">
                        <li>
                            <span class="label">Product</span>
                            <span class="value">Custom <?php echo htmlspecialchars($typeName); ?></span>
                        </li>
                        <li>
                            <span class="label">Apparel Color</span>
                            <span class="value"><span class="color-swatch" style="background:<?php echo htmlspecialchars($apparelColor); ?>"></span> <?php echo htmlspecialchars($apparelColor); ?></span>
                        </li>
                        <li>
                            <span class="label">Size</span>
                            <span class="value"><?php echo htmlspecialchars($size); ?></span>
                        </li>
                        <li>
                            <span class="label">Quantity</span>
                            <span class="value"><?php echo $quantity; ?></span>
                        </li>
                        <li>
                            <span class="label">Print Size</span>
                            <span class="value"><?php echo htmlspecialchars($printSizeNames[$printSize] ?? 'Medium'); ?></span>
                        </li>
                        <li>
                            <span class="label">Colors Used</span>
                            <span class="value"><?php echo $colorsUsed; ?> color<?php echo $colorsUsed > 1 ? 's' : ''; ?></span>
                        </li>
                        <?php if ($discountType !== 'regular'): ?>
                        <li>
                            <span class="label">Discount</span>
                            <span class="value" style="color:#27ae60;"><?php echo ucfirst($discountType); ?> (20% off)</span>
                        </li>
                        <?php endif; ?>
                        <?php if ($design['notes']): ?>
                        <li>
                            <span class="label">Notes</span>
                            <span class="value" style="max-width:60%; text-align:right;"><?php echo htmlspecialchars($design['notes']); ?></span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Price Breakdown -->
    <div class="summary-card">
        <div class="summary-card-header">
            <i class="fas fa-receipt me-2"></i>Price Breakdown
        </div>
        <div class="summary-card-body">
            <ul class="price-breakdown">
                <li>
                    <span><?php echo htmlspecialchars($typeName); ?> Base Price</span>
                    <span>₱<?php echo number_format($basePrice, 2); ?></span>
                </li>
                <li>
                    <span>Custom Print (<?php echo htmlspecialchars($printSizeNames[$printSize] ?? 'Medium'); ?>)</span>
                    <span>₱<?php echo number_format($printCost, 2); ?></span>
                </li>
                <?php if ($colorCost > 0): ?>
                <li>
                    <span>Extra Colors (<?php echo $colorsUsed - 1; ?> × ₱25)</span>
                    <span>₱<?php echo number_format($colorCost, 2); ?></span>
                </li>
                <?php endif; ?>
                <li>
                    <span>Unit Price</span>
                    <span>₱<?php echo number_format($unitPrice, 2); ?></span>
                </li>
                <?php if ($quantity > 1): ?>
                <li>
                    <span>Quantity × <?php echo $quantity; ?></span>
                    <span>₱<?php echo number_format($subtotal, 2); ?></span>
                </li>
                <?php endif; ?>
                <?php if ($discountAmount > 0): ?>
                <li class="discount">
                    <span><?php echo ucfirst($discountType); ?> Discount (20%)</span>
                    <span>-₱<?php echo number_format($discountAmount, 2); ?></span>
                </li>
                <?php endif; ?>
                <li class="total-row">
                    <span>Total</span>
                    <span>₱<?php echo number_format($totalPrice, 2); ?></span>
                </li>
            </ul>
        </div>
    </div>

    <!-- Proceed to Payment -->
    <form method="POST">
        <input type="hidden" name="create_order" value="1">
        <input type="hidden" name="notes" value="<?php echo htmlspecialchars($design['notes'] ?? ''); ?>">
        <button type="submit" class="btn-proceed">
            <i class="fas fa-credit-card me-2"></i>Proceed to Payment — ₱<?php echo number_format($totalPrice, 2); ?>
        </button>
    </form>
    <a href="custom-design.php" class="btn-back">
        <i class="fas fa-arrow-left me-2"></i>Back to Design Tool
    </a>
</div>

<?php include 'includes/footer/footer.php'; ?>
