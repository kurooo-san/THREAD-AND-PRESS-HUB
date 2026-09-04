<?php
require 'includes/config.php';
$pageTitle = 'Promotions';

// Load active coupons from DB (graceful fallback if table missing)
$coupons = [];
if (couponsTableExists()) {
    $res = $conn->query("SELECT * FROM coupons
                         WHERE is_active = 1
                           AND (valid_until IS NULL OR valid_until >= NOW())
                         ORDER BY discount_value DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $coupons[] = $row;
        }
    }
}

$gradients = [
    'linear-gradient(135deg, #1a1a1a 0%, #333 100%)',
    'linear-gradient(135deg, #667bc0 0%, #8a9dd8 100%)',
    'linear-gradient(135deg, #f5a623 0%, #f8b739 100%)',
    'linear-gradient(135deg, #2ecc71 0%, #27ae60 100%)',
    'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)',
    'linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%)',
    'linear-gradient(135deg, #3498db 0%, #2980b9 100%)',
];
?>

<?php include 'includes/header/header.php'; ?>

<div class="container my-5">
    <div class="mb-5">
        <h1 class="display-5" style="font-weight: 800; margin-bottom: 1rem;">Current Promotions</h1>
        <p class="lead text-muted">Don't miss out on our exclusive deals and limited-time offers</p>
    </div>

    <?php if (empty($coupons)): ?>
        <div class="alert alert-info text-center my-5 p-5" style="border-radius: var(--radius-lg);">
            <i class="fas fa-tags fa-2x mb-3"></i>
            <h4>No Active Promotions Right Now</h4>
            <p class="mb-3">Check back soon — new offers are added regularly!</p>
            <a href="shop.php" class="btn btn-dark">Shop Now</a>
        </div>
    <?php else: ?>
        <!-- Featured Promotion: highest-value coupon -->
        <?php $featured = $coupons[0]; ?>
        <div class="row mb-5">
            <div class="col-12">
                <div class="card border-0 p-5" style="background: <?php echo $gradients[0]; ?>; color: white; border-radius: var(--radius-lg);">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h2 style="font-weight: 800; font-size: 2.5rem; margin-bottom: 1rem;">
                                🎉
                                <?php
                                if ($featured['discount_type'] === 'percent') {
                                    echo 'Save up to ' . rtrim(rtrim(number_format($featured['discount_value'], 2), '0'), '.') . '%!';
                                } else {
                                    echo '₱' . number_format($featured['discount_value'], 0) . ' OFF!';
                                }
                                ?>
                            </h2>
                            <p style="font-size: 1.2rem; margin-bottom: 1.5rem;">
                                <?php echo htmlspecialchars($featured['description'] ?: 'Limited-time offer'); ?>.
                                Use code <strong><?php echo htmlspecialchars($featured['code']); ?></strong> at checkout.
                            </p>
                            <p style="font-size: 0.95rem; opacity: 0.95;">
                                <?php
                                $bits = [];
                                if (!empty($featured['valid_until'])) $bits[] = 'Valid until ' . date('M d, Y', strtotime($featured['valid_until']));
                                if ((float)$featured['min_subtotal'] > 0) $bits[] = 'Min. spend ₱' . number_format($featured['min_subtotal'], 2);
                                echo htmlspecialchars(implode(' · ', $bits)) ?: 'Available now';
                                ?>
                            </p>
                        </div>
                        <div class="col-lg-4 text-center">
                            <a href="shop.php" class="btn btn-light btn-lg" style="padding: 0.75rem 2rem; font-weight: 700;">Shop Now</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Promotions Grid -->
        <div class="row g-4 mb-5">
            <?php foreach ($coupons as $i => $c):
                $bg = $gradients[($i + 1) % count($gradients)];
                $isPercent = $c['discount_type'] === 'percent';
                $bigText = $isPercent
                    ? rtrim(rtrim(number_format($c['discount_value'], 2), '0'), '.') . '% OFF'
                    : '₱' . number_format($c['discount_value'], 0) . ' OFF';
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 promo-card">
                    <div style="background: <?php echo $bg; ?>; height: 200px; display: flex; align-items: center; justify-content: center; color: white;">
                        <div class="text-center">
                            <h3 style="font-weight: 800; font-size: 2.5rem; margin: 0;"><?php echo $bigText; ?></h3>
                            <p style="margin-top: 0.5rem; font-size: 0.9rem;">Code: <strong><?php echo htmlspecialchars($c['code']); ?></strong></p>
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title" style="font-weight: 700;"><?php echo htmlspecialchars($c['description'] ?: $c['code']); ?></h5>
                        <p class="card-text text-muted">
                            <?php if ((float)$c['min_subtotal'] > 0): ?>
                                Minimum order of ₱<?php echo number_format($c['min_subtotal'], 2); ?> required.
                            <?php else: ?>
                                No minimum purchase required.
                            <?php endif; ?>
                        </p>
                        <small class="text-muted">
                            <i class="fas fa-calendar"></i>
                            <?php echo !empty($c['valid_until']) ? 'Valid until ' . date('M d, Y', strtotime($c['valid_until'])) : 'Ongoing offer'; ?>
                        </small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Terms Section -->
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card border-0 bg-light p-4">
                <h5 style="font-weight: 700; margin-bottom: 1rem;">Terms &amp; Conditions</h5>
                <ul class="text-muted small">
                    <li>Only one coupon code may be applied per order.</li>
                    <li>Discounts apply to the subtotal only (before delivery fee).</li>
                    <li>Coupons cannot be combined with PWD/Senior discounts unless stated.</li>
                    <li>Some coupons require a minimum order amount or are limited in number of uses.</li>
                    <li>Thread &amp; Press Hub reserves the right to modify or cancel promotions at any time.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
    .promo-card { transition: transform 0.3s ease, box-shadow 0.3s ease; overflow: hidden; }
    .promo-card:hover { transform: translateY(-8px); box-shadow: 0 12px 24px rgba(0,0,0,0.12) !important; }
</style>

<?php include 'includes/footer/footer.php'; ?>
