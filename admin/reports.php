<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Sales Report';

// ---------------------------------------------------------------
// Date range (inclusive). Defaults to this month so far.
// ---------------------------------------------------------------
function rpDate($value, $fallback) {
    $d = DateTime::createFromFormat('!Y-m-d', (string)$value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : $fallback;
}
$today = date('Y-m-d');
$from  = rpDate($_GET['from'] ?? '', date('Y-m-01'));
$to    = rpDate($_GET['to'] ?? '', $today);
if ($from > $to) { [$from, $to] = [$to, $from]; }
$fromSql = $from . ' 00:00:00';
$toSql   = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'; // exclusive end

$presets = [
    'This month'   => [date('Y-m-01'), $today],
    'Last month'   => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
    'Last 30 days' => [date('Y-m-d', strtotime('-29 days')), $today],
    'This year'    => [date('Y-01-01'), $today],
];

function rpHas(mysqli $conn, string $sql): bool {
    $r = $conn->query($sql);
    return $r !== false && $r->num_rows > 0;
}
$hasCustomOrders = rpHas($conn, "SHOW TABLES LIKE 'custom_orders'");
$hasCouponCols   = rpHas($conn, "SHOW COLUMNS FROM orders LIKE 'coupon_code'");

// ---------------------------------------------------------------
// Every order in range, shop + custom, newest first.
// ponytail: loads the whole range into memory; fine for a shop this size,
// move the totals into SQL GROUP BYs if a year runs to tens of thousands of orders.
// ---------------------------------------------------------------
// Two queries merged in PHP rather than a UNION: orders and custom_orders use
// different collations, which MySQL refuses to UNION.
function rpFetch(mysqli $conn, string $sql, string $from, string $to): array {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $from, $to);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}
$couponSel = $hasCouponCols ? "o.coupon_discount, o.coupon_code" : "0 AS coupon_discount, NULL AS coupon_code";
$rows = rpFetch($conn, "SELECT o.id, o.created_at, u.fullname, 'Shop' AS kind, o.status, o.payment_method, o.payment_status,
                               o.subtotal, o.discount_amount, $couponSel, o.delivery_fee, o.total
                        FROM orders o LEFT JOIN users u ON u.id = o.user_id
                        WHERE o.created_at >= ? AND o.created_at < ?", $fromSql, $toSql);
if ($hasCustomOrders) {
    $rows = array_merge($rows, rpFetch($conn, "SELECT c.id, c.created_at, u.fullname, 'Custom' AS kind, c.status, 'custom' AS payment_method,
                                                      NULL AS payment_status, c.subtotal, c.discount_amount, 0 AS coupon_discount,
                                                      NULL AS coupon_code, 0 AS delivery_fee, c.total_price AS total
                                               FROM custom_orders c LEFT JOIN users u ON u.id = c.user_id
                                               WHERE c.created_at >= ? AND c.created_at < ?", $fromSql, $toSql));
}
usort($rows, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

// ---------------------------------------------------------------
// CSV export — same rows, before any HTML is sent.
// ---------------------------------------------------------------
if (($_GET['export'] ?? '') === 'csv') {
    logAudit('report_export', 'report', null, "$from to $to");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales-report_' . $from . '_to_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads ₱ and ñ correctly
    fputcsv($out, ['Sales Report', "$from to $to"]);
    fputcsv($out, ['Order', 'Date', 'Customer', 'Type', 'Status', 'Payment', 'Subtotal', 'Discount', 'Coupon', 'Coupon discount', 'Delivery fee', 'Total']);
    foreach ($rows as $r) {
        fputcsv($out, [
            ($r['kind'] === 'Custom' ? 'C-' : '#') . $r['id'],
            $r['created_at'],
            $r['fullname'] ?? '(deleted user)',
            $r['kind'],
            $r['status'],
            strtoupper($r['payment_method']),
            number_format((float)$r['subtotal'], 2, '.', ''),
            number_format((float)$r['discount_amount'], 2, '.', ''),
            $r['coupon_code'] ?? '',
            number_format((float)$r['coupon_discount'], 2, '.', ''),
            number_format((float)$r['delivery_fee'], 2, '.', ''),
            number_format((float)$r['total'], 2, '.', ''),
        ]);
    }
    fclose($out);
    exit();
}

// ---------------------------------------------------------------
// Totals. Sales exclude cancelled orders, same rule as the dashboard.
// ---------------------------------------------------------------
$sum = ['sales' => 0.0, 'orders' => 0, 'discounts' => 0.0, 'coupons' => 0.0, 'delivery' => 0.0, 'cancelled' => 0, 'cancelled_value' => 0.0];
$byDay = [];
$byPayment = [];
for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
    $byDay[$d] = ['orders' => 0, 'sales' => 0.0];
}
foreach ($rows as $r) {
    if ($r['status'] === 'cancelled') {
        $sum['cancelled']++;
        $sum['cancelled_value'] += (float)$r['total'];
        continue;
    }
    $total = (float)$r['total'];
    $sum['sales']     += $total;
    $sum['orders']++;
    $sum['discounts'] += (float)$r['discount_amount'];
    $sum['coupons']   += (float)$r['coupon_discount'];
    $sum['delivery']  += (float)$r['delivery_fee'];

    $day = substr($r['created_at'], 0, 10);
    if (isset($byDay[$day])) {
        $byDay[$day]['orders']++;
        $byDay[$day]['sales'] += $total;
    }
    $pm = $r['kind'] === 'Custom' ? 'Custom orders' : strtoupper($r['payment_method']);
    $byPayment[$pm] = ($byPayment[$pm] ?? ['orders' => 0, 'sales' => 0.0]);
    $byPayment[$pm]['orders']++;
    $byPayment[$pm]['sales'] += $total;
}
$avgOrder = $sum['orders'] ? $sum['sales'] / $sum['orders'] : 0;
arsort($byPayment);

// Top products by quantity (shop orders only; custom orders are one-off designs).
$stmt = $conn->prepare("SELECT p.name, SUM(oi.quantity) AS qty, SUM(oi.subtotal) AS sales
                        FROM order_items oi
                        JOIN orders o   ON o.id = oi.order_id
                        JOIN products p ON p.id = oi.product_id
                        WHERE o.status <> 'cancelled' AND o.created_at >= ? AND o.created_at < ?
                        GROUP BY p.id, p.name
                        ORDER BY qty DESC, sales DESC
                        LIMIT 10");
$stmt->bind_param("ss", $fromSql, $toSql);
$stmt->execute();
$topProducts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$rangeLabel = date('M d, Y', strtotime($from)) . ' – ' . date('M d, Y', strtotime($to));
$exportUrl  = 'reports.php?' . http_build_query(['from' => $from, 'to' => $to, 'export' => 'csv']);
$peso = fn($n) => '₱' . number_format((float)$n, 2);
?>

<?php include '../includes/header/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<style>
    .rp-presets a { margin: 0 .25rem .25rem 0; }
    .rp-stat h5 { font-weight: 800; margin-bottom: .25rem; }
    .rp-print-head { display: none; }
    @media print {
        .admin-sidebar, nav, header, footer, .no-print, .ad-mobile-bar { display: none !important; }
        .admin-main-content, .admin-container { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .admin-card { box-shadow: none !important; border: 1px solid #ccc !important; break-inside: avoid; }
        .rp-print-head { display: block; }
        body { background: #fff !important; }
    }
</style>

<div class="admin-container">
    <div class="rp-print-head mb-3">
        <h2 style="margin:0;">Thread &amp; Press — Sales Report</h2>
        <div><?php echo $rangeLabel; ?> · Generated <?php echo date('M d, Y H:i'); ?></div>
    </div>

    <div class="mb-4 no-print">
        <h1 class="text-coffee-dark mb-2" style="font-size: 2rem; font-weight: 800;">
            <i class="fas fa-chart-line"></i> Sales Report
        </h1>
        <p class="text-muted">Sales for <?php echo $rangeLabel; ?>. Cancelled orders are not counted as sales.</p>
    </div>

    <div class="admin-card mb-4 no-print">
        <form method="GET" class="d-flex gap-3 align-items-end flex-wrap">
            <div>
                <label class="form-label small fw-bold" for="rpFrom">From</label>
                <input type="date" class="form-control form-control-sm" id="rpFrom" name="from" value="<?php echo $from; ?>" max="<?php echo $today; ?>">
            </div>
            <div>
                <label class="form-label small fw-bold" for="rpTo">To</label>
                <input type="date" class="form-control form-control-sm" id="rpTo" name="to" value="<?php echo $to; ?>" max="<?php echo $today; ?>">
            </div>
            <div>
                <button type="submit" class="btn btn-sm btn-dark">Show report</button>
            </div>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-dark" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-csv"></i> Export CSV</a>
            </div>
        </form>
        <div class="rp-presets mt-2">
            <?php foreach ($presets as $label => [$pf, $pt]): ?>
            <a href="reports.php?<?php echo http_build_query(['from' => $pf, 'to' => $pt]); ?>"
               class="btn btn-sm <?php echo ($pf === $from && $pt === $to) ? 'btn-secondary' : 'btn-outline-secondary'; ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="admin-card text-center rp-stat">
            <h5><?php echo $peso($sum['sales']); ?></h5><p class="mb-0 text-muted small">Total sales</p>
        </div></div>
        <div class="col-6 col-md-3"><div class="admin-card text-center rp-stat">
            <h5><?php echo number_format($sum['orders']); ?></h5><p class="mb-0 text-muted small">Orders</p>
        </div></div>
        <div class="col-6 col-md-3"><div class="admin-card text-center rp-stat">
            <h5><?php echo $peso($avgOrder); ?></h5><p class="mb-0 text-muted small">Average order</p>
        </div></div>
        <div class="col-6 col-md-3"><div class="admin-card text-center rp-stat">
            <h5><?php echo $peso($sum['discounts'] + $sum['coupons']); ?></h5>
            <p class="mb-0 text-muted small">Discounts given<br><span style="font-size:.75rem;">PWD/Senior <?php echo $peso($sum['discounts']); ?> · Coupons <?php echo $peso($sum['coupons']); ?></span></p>
        </div></div>
        <div class="col-6 col-md-3"><div class="admin-card text-center rp-stat">
            <h5><?php echo $peso($sum['delivery']); ?></h5><p class="mb-0 text-muted small">Delivery fees</p>
        </div></div>
        <div class="col-6 col-md-3"><div class="admin-card text-center rp-stat">
            <h5><?php echo number_format($sum['cancelled']); ?></h5><p class="mb-0 text-muted small">Cancelled (<?php echo $peso($sum['cancelled_value']); ?>)</p>
        </div></div>
    </div>

    <div class="admin-card mb-4">
        <h5 class="mb-3"><i class="fas fa-chart-bar"></i> Daily sales</h5>
        <?php if ($sum['orders'] === 0): ?>
            <p class="text-muted text-center py-4 mb-0">No sales in this period.</p>
        <?php else: ?>
            <div style="position: relative; height: 280px;"><canvas id="rpChart"></canvas></div>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="admin-card h-100">
                <h5 class="mb-3"><i class="fas fa-trophy"></i> Top products</h5>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>#</th><th>Product</th><th class="text-end">Qty sold</th><th class="text-end">Sales</th></tr></thead>
                        <tbody>
                        <?php if (!$topProducts): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No products sold in this period.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($topProducts as $i => $p): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><?php echo htmlspecialchars($p['name']); ?></td>
                                <td class="text-end"><?php echo (int)$p['qty']; ?></td>
                                <td class="text-end"><?php echo $peso($p['sales']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mt-2 mb-0">Item prices before discounts, VAT and delivery.</p>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="admin-card h-100">
                <h5 class="mb-3"><i class="fas fa-wallet"></i> By payment method</h5>
                <table class="table table-sm mb-0">
                    <thead><tr><th>Method</th><th class="text-end">Orders</th><th class="text-end">Sales</th></tr></thead>
                    <tbody>
                    <?php if (!$byPayment): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No sales in this period.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($byPayment as $method => $v): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($method); ?></td>
                            <td class="text-end"><?php echo $v['orders']; ?></td>
                            <td class="text-end"><?php echo $peso($v['sales']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <h5 class="mb-3"><i class="fas fa-list"></i> Orders (<?php echo count($rows); ?>)</h5>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Order</th><th>Date</th><th>Customer</th><th>Status</th><th>Payment</th>
                        <th class="text-end">Discounts</th><th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No orders in this period.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): $isCustom = $r['kind'] === 'Custom'; ?>
                    <tr<?php echo $r['status'] === 'cancelled' ? ' class="text-muted" style="text-decoration: line-through;"' : ''; ?>>
                        <td>
                            <?php if ($isCustom): ?>
                                C-<?php echo (int)$r['id']; ?>
                            <?php else: ?>
                                <a href="order_details.php?id=<?php echo (int)$r['id']; ?>">#<?php echo (int)$r['id']; ?></a>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?php echo date('M d, Y H:i', strtotime($r['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($r['fullname'] ?? '(deleted user)'); ?></td>
                        <td class="small"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $r['status']))); ?></td>
                        <td class="small"><?php echo $isCustom ? 'Custom order' : htmlspecialchars(strtoupper($r['payment_method'])); ?></td>
                        <td class="text-end small">
                            <?php $disc = (float)$r['discount_amount'] + (float)$r['coupon_discount']; echo $disc > 0 ? '-' . $peso($disc) : '—'; ?>
                            <?php if (!empty($r['coupon_code'])): ?><br><span class="text-muted"><?php echo htmlspecialchars($r['coupon_code']); ?></span><?php endif; ?>
                        </td>
                        <td class="text-end fw-bold"><?php echo $peso($r['total']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->
<script src="../js/admin-sidebar.js"></script>

<?php if ($sum['orders'] > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const el = document.getElementById('rpChart');
    if (!el || typeof Chart === 'undefined') return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_map(fn($d) => date('M j', strtotime($d)), array_keys($byDay))); ?>,
            datasets: [{
                label: 'Sales (₱)',
                data: <?php echo json_encode(array_map(fn($v) => round($v['sales'], 2), array_values($byDay))); ?>,
                backgroundColor: '#d9884c',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => '₱' + c.parsed.y.toLocaleString(undefined, { minimumFractionDigits: 2 }) } }
            },
            scales: { y: { beginAtZero: true, ticks: { callback: v => '₱' + v.toLocaleString() } } }
        }
    });
})();
</script>
<?php endif; ?>

<?php include '../includes/footer/footer.php'; ?>
