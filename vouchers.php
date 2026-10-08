<?php
require 'includes/config.php';
redirectToLogin();

$pageTitle = 'My Vouchers';

// Every voucher this customer was ever given, sorted into three shelves.
$shelves = ['ready' => [], 'used' => [], 'gone' => []];
foreach (voucherWallet((int) $_SESSION['user_id'], false) as $v) {
    if ($v['used_at']) {
        $shelves['used'][] = $v;
    } elseif (in_array(couponStatus($v)[0], ['Active', 'Scheduled'], true)) {
        $shelves['ready'][] = $v;
    } else {
        $shelves['gone'][] = $v;
    }
}
$titles = [
    'ready' => ['Ready to use', 'Pick these at checkout (scheduled ones once they start) — no code to type.'],
    'used'  => ['Used', null],
    'gone'  => ['Expired or unavailable', null],
];
?>

<?php include 'includes/header/header.php'; ?>

<div class="container py-4" style="max-width: 760px;">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb" style="font-size:0.85rem;">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active">My Vouchers</li>
        </ol>
    </nav>
    <h1 style="font-weight:800; font-size:2rem; margin-bottom:0.5rem;">My Vouchers</h1>
    <p class="text-muted mb-4">Vouchers from the Thread &amp; Press team. At checkout we automatically pick the one that saves you the most, and you can use one discount and one shipping voucher on the same order.</p>

    <?php if (!array_filter($shelves)): ?>
        <div class="text-center text-muted py-5">
            <i class="fas fa-ticket-alt fa-2x mb-3"></i>
            <p class="mb-0">No vouchers yet. When our team sends you one, it will show up here and we'll email you.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($shelves as $shelf => $list): if (!$list) continue; [$title, $hint] = $titles[$shelf]; ?>
    <section class="mb-4">
        <h2 class="voucher-group-title" style="font-size:0.8rem;"><?php echo $title; ?> (<?php echo count($list); ?>)</h2>
        <?php if ($hint): ?><p class="small text-muted mb-2"><?php echo $hint; ?></p><?php endif; ?>
        <?php foreach ($list as $v): ?>
        <div class="voucher-card voucher-card--static<?php echo $shelf !== 'ready' ? ' is-locked' : ''; ?>">
            <span class="voucher-stub voucher-stub--<?php echo htmlspecialchars($v['kind']); ?>"><?php echo htmlspecialchars(voucherLabel($v)); ?></span>
            <span class="voucher-body">
                <span class="voucher-title"><?php echo htmlspecialchars($v['description'] ?: $v['code']); ?></span>
                <?php if (!empty($v['note'])): ?>
                <span class="voucher-note">“<?php echo htmlspecialchars($v['note']); ?>”</span>
                <?php endif; ?>
                <span class="voucher-meta">
                    <?php echo (float) $v['min_subtotal'] > 0 ? 'Min. spend ₱' . number_format((float) $v['min_subtotal'], 2) : 'No minimum spend'; ?>
                    · <?php echo $v['kind'] === 'shipping' ? 'Delivery orders' : 'Shop orders'; ?>
                </span>
                <span class="voucher-meta">
                    <?php if ($shelf === 'used'): ?>
                        Used <?php echo date('M d, Y', strtotime($v['used_at'])); ?>
                        <?php if ($v['order_id']): ?>· <a href="order_details.php?id=<?php echo (int) $v['order_id']; ?>">Order #<?php echo (int) $v['order_id']; ?></a><?php endif; ?>
                    <?php elseif ($shelf === 'gone'): ?>
                        <?php echo htmlspecialchars(couponStatus($v)[0]); ?>
                    <?php elseif (couponStatus($v)[0] === 'Scheduled'): ?>
                        Starts <?php echo date('M d, Y g:i A', strtotime($v['valid_from'])); ?>
                    <?php else: ?>
                        <?php echo $v['valid_until'] ? 'Valid until ' . date('M d, Y g:i A', strtotime($v['valid_until'])) : 'No expiry'; ?>
                    <?php endif; ?>
                </span>
            </span>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endforeach; ?>

    <a href="shop.php" class="btn btn-dark mt-2"><i class="fas fa-shopping-bag me-1"></i> Shop now</a>
</div>

<?php include 'includes/footer/footer.php'; ?>
