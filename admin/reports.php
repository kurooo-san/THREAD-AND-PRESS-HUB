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
$hasShipVoucher  = rpHas($conn, "SHOW COLUMNS FROM orders LIKE 'shipping_discount'");

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
// A shipping voucher only lowers delivery_fee; show its amount and code next to the item voucher.
$couponSel .= $hasShipVoucher
    ? ", o.shipping_discount, o.shipping_coupon_code"
    : ", 0 AS shipping_discount, NULL AS shipping_coupon_code";
$rows = rpFetch($conn, "SELECT o.id, o.created_at, u.fullname, 'Shop' AS kind, o.status, o.payment_method, o.payment_status,
                               o.subtotal, o.discount_amount, $couponSel, o.delivery_fee, o.total
                        FROM orders o LEFT JOIN users u ON u.id = o.user_id
                        WHERE o.created_at >= ? AND o.created_at < ?", $fromSql, $toSql);
if ($hasCustomOrders) {
    $rows = array_merge($rows, rpFetch($conn, "SELECT c.id, c.created_at, u.fullname, 'Custom' AS kind, c.status, 'custom' AS payment_method,
                                                      NULL AS payment_status, c.subtotal, c.discount_amount, 0 AS coupon_discount,
                                                      NULL AS coupon_code, 0 AS shipping_discount, NULL AS shipping_coupon_code, 0 AS delivery_fee, c.total_price AS total
                                               FROM custom_orders c LEFT JOIN users u ON u.id = c.user_id
                                               WHERE c.created_at >= ? AND c.created_at < ?", $fromSql, $toSql));
}
usort($rows, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

// ---------------------------------------------------------------
// Totals. Sales exclude cancelled orders, same rule as the dashboard.
// ---------------------------------------------------------------
$sum = ['sales' => 0.0, 'orders' => 0, 'discounts' => 0.0, 'coupons' => 0.0, 'shipping' => 0.0, 'delivery' => 0.0, 'cancelled' => 0, 'cancelled_value' => 0.0];
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
    $sum['shipping']  += (float)$r['shipping_discount'];
    $sum['delivery']  += (float)$r['delivery_fee'];

    $day = substr($r['created_at'], 0, 10);
    if (isset($byDay[$day])) {
        $byDay[$day]['orders']++;
        $byDay[$day]['sales'] += $total;
    }
    $pm = $r['kind'] === 'Custom' ? 'Custom orders' : paymentMethodLabel($r['payment_method']);
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

// ---------------------------------------------------------------
// Excel export: the same figures as the page, one sheet per section.
// ---------------------------------------------------------------
if (($_GET['export'] ?? '') === 'xlsx') {
    require_once __DIR__ . '/../includes/xlsx-writer.php';
    $period = 'Period: ' . $rangeLabel;
    $made   = 'Generated ' . date('M d, Y g:i A');
    $label  = fn($v) => ucwords(str_replace('_', ' ', (string)$v));
    $m      = fn($v) => ['v' => round((float)$v, 2), 's' => 'money'];
    $hdr    = fn(array $cols) => array_map(fn($c) => ['v' => $c, 's' => 'header'], $cols);

    // Summary
    $summary = [
        [['v' => 'Thread & Press Hub — Sales Report', 's' => 'title']],
        [['v' => $period, 's' => 'muted']],
        [['v' => $made, 's' => 'muted']],
        [],
        $hdr(['Summary', 'Value']),
        ['Total sales', $m($sum['sales'])],
        ['Orders', ['v' => $sum['orders'], 's' => 'int']],
        ['Average order', $m($avgOrder)],
        ['PWD / Senior discounts', $m($sum['discounts'])],
        ['Voucher discounts (items)', $m($sum['coupons'])],
        ['Shipping voucher discounts', $m($sum['shipping'])],
        ['Delivery fees', $m($sum['delivery'])],
        ['Cancelled orders', ['v' => $sum['cancelled'], 's' => 'int']],
        ['Value of cancelled orders', $m($sum['cancelled_value'])],
        [],
        [['v' => 'Sales = totals of non-cancelled shop and custom orders, the same rule as the Dashboard.', 's' => 'muted']],
        [],
        $hdr(['Payment method', 'Orders', 'Sales']),
    ];
    foreach ($byPayment as $method => $v) {
        $summary[] = [$method, ['v' => $v['orders'], 's' => 'int'], $m($v['sales'])];
    }
    $summary[] = [['v' => 'Total', 's' => 'total'], ['v' => $sum['orders'], 's' => 'totalInt'], ['v' => round($sum['sales'], 2), 's' => 'totalMoney']];

    // Orders: header on row 5, data from row 6, totals skip cancelled rows.
    $orders = [
        [['v' => 'Orders', 's' => 'title']],
        [['v' => $period, 's' => 'muted']],
        [['v' => 'Cancelled orders are listed but left out of the totals row.', 's' => 'muted']],
        [],
        $hdr(['Order', 'Date', 'Customer', 'Type', 'Status', 'Payment', 'Payment status', 'Subtotal', 'PWD/Senior discount', 'Vouchers', 'Voucher discount', 'Shipping voucher', 'Delivery fee', 'Total']),
    ];
    foreach ($rows as $r) {
        $orders[] = [
            ($r['kind'] === 'Custom' ? 'C-' : '#') . $r['id'],
            ['v' => xlsxDate($r['created_at']), 's' => 'datetime'],
            $r['fullname'] ?? '(deleted user)',
            $r['kind'],
            $label($r['status']),
            $r['kind'] === 'Custom' ? 'Custom order' : paymentMethodLabel($r['payment_method']),
            $r['payment_status'] ? $label($r['payment_status']) : '',
            $m($r['subtotal']),
            $m($r['discount_amount']),
            implode(' + ', array_filter([$r['coupon_code'], $r['shipping_coupon_code']])),
            $m($r['coupon_discount']),
            $m($r['shipping_discount']),
            $m($r['delivery_fee']),
            $m($r['total']),
        ];
    }
    $first = 6;
    $last  = 5 + count($rows);
    if ($rows) {
        $sumIf = fn($col) => ['f' => "SUMIFS({$col}{$first}:{$col}{$last},E{$first}:E{$last},\"<>Cancelled\")", 's' => 'totalMoney'];
        $orders[] = [
            ['v' => 'Total (excl. cancelled)', 's' => 'total'], ['v' => '', 's' => 'total'], ['v' => '', 's' => 'total'],
            ['v' => '', 's' => 'total'], ['v' => '', 's' => 'total'], ['v' => '', 's' => 'total'], ['v' => '', 's' => 'total'],
            $sumIf('H'), $sumIf('I'), ['v' => '', 's' => 'total'], $sumIf('K'), $sumIf('L'), $sumIf('M'), $sumIf('N'),
        ];
    } else {
        $orders[] = [['v' => 'No orders in this period.', 's' => 'muted']];
    }

    // Top products
    $products = [
        [['v' => 'Top Products', 's' => 'title']],
        [['v' => $period, 's' => 'muted']],
        [],
        $hdr(['Rank', 'Product', 'Qty sold', 'Sales']),
    ];
    foreach ($topProducts as $i => $p) {
        $products[] = [$i + 1, $p['name'], ['v' => (int)$p['qty'], 's' => 'int'], $m($p['sales'])];
    }
    if (!$topProducts) $products[] = [['v' => 'No products sold in this period.', 's' => 'muted']];
    $products[] = [];
    $products[] = [['v' => 'Item prices before discounts, VAT and delivery. Shop orders only.', 's' => 'muted']];

    // Daily sales
    $daily = [
        [['v' => 'Daily Sales', 's' => 'title']],
        [['v' => $period, 's' => 'muted']],
        [],
        $hdr(['Date', 'Orders', 'Sales']),
    ];
    foreach ($byDay as $day => $v) {
        $daily[] = [['v' => xlsxDate($day), 's' => 'date'], ['v' => $v['orders'], 's' => 'int'], $m($v['sales'])];
    }
    $daily[] = [['v' => 'Total', 's' => 'total'], ['v' => $sum['orders'], 's' => 'totalInt'], ['v' => round($sum['sales'], 2), 's' => 'totalMoney']];

    $file = xlsxBuild([
        ['name' => 'Summary',      'widths' => [30, 18, 18],                                               'rows' => $summary],
        ['name' => 'Orders',       'widths' => [10, 22, 26, 9, 18, 24, 18, 13, 23, 16, 19, 18, 14, 14], 'rows' => $orders,
         'freeze' => 5, 'filter' => $rows ? "A5:N{$last}" : null],
        ['name' => 'Top Products', 'widths' => [8, 40, 12, 16],                                           'rows' => $products, 'freeze' => 4],
        ['name' => 'Daily Sales',  'widths' => [22, 10, 16],                                              'rows' => $daily, 'freeze' => 4],
    ]);
    if ($file === null) {
        http_response_code(500);
        exit('Excel export needs the PHP zip extension, which is not enabled on this server.');
    }
    logAudit('report_export', 'report', null, "$from to $to");
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="sales-report_' . $from . '_to_' . $to . '.xlsx"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    unlink($file);
    exit();
}
$exportUrl  = 'reports.php?' . http_build_query(['from' => $from, 'to' => $to, 'export' => 'xlsx']);
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
                <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel"></i> Export Excel</a>
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
            <h5><?php echo $peso($sum['discounts'] + $sum['coupons'] + $sum['shipping']); ?></h5>
            <p class="mb-0 text-muted small">Discounts given<br><span style="font-size:.75rem;">PWD/Senior <?php echo $peso($sum['discounts']); ?> · Vouchers <?php echo $peso($sum['coupons']); ?> · Shipping <?php echo $peso($sum['shipping']); ?></span></p>
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
                        <td class="small"><?php echo $isCustom ? 'Custom order' : htmlspecialchars(paymentMethodLabel($r['payment_method'])); ?></td>
                        <td class="text-end small">
                            <?php $disc = (float)$r['discount_amount'] + (float)$r['coupon_discount'] + (float)$r['shipping_discount']; echo $disc > 0 ? '-' . $peso($disc) : '—'; ?>
                            <?php $codes = implode(' + ', array_filter([$r['coupon_code'], $r['shipping_coupon_code']])); if ($codes !== ''): ?><br><span class="text-muted"><?php echo htmlspecialchars($codes); ?></span><?php endif; ?>
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
