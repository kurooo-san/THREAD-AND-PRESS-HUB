<?php
require '../includes/config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Admin Dashboard';

/** True when a table exists. query() returns false on error, hence the check. */
function adHasTable(mysqli $conn, string $table): bool
{
    $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    return $r !== false && $r->num_rows > 0;
}

$hasCustomOrders  = adHasTable($conn, 'custom_orders');
$hasCustomDesigns = adHasTable($conn, 'custom_designs');

/** Scalar helper — returns the first column of the first row, or $default. */
function adScalar(mysqli $conn, string $sql, float $default = 0): float
{
    $r = $conn->query($sql);
    if (!$r) { return $default; }
    $row = $r->fetch_row();
    return $row ? (float)$row[0] : $default;
}

/**
 * Period-over-period trend. Returns [direction, label] where direction is
 * one of up|down|flat, matching the .ad-trend modifier classes.
 */
function adTrend(float $now, float $prev): array
{
    if ($prev <= 0) {
        return $now > 0 ? ['up', 'New'] : ['flat', 'No change'];
    }
    $pct = (($now - $prev) / $prev) * 100;
    if ($pct >= 0.05)  { return ['up',   '↑ ' . number_format($pct, 1) . '%']; }
    if ($pct <= -0.05) { return ['down', '↓ ' . number_format(abs($pct), 1) . '%']; }
    return ['flat', 'No change'];
}

// ---------------------------------------------------------------
// Headline figures
// ---------------------------------------------------------------
$users_count    = (int)adScalar($conn, "SELECT COUNT(*) FROM users");
$products_count = (int)adScalar($conn, "SELECT COUNT(*) FROM products WHERE status = 'active'");
$orders_count   = (int)adScalar($conn, "SELECT COUNT(*) FROM orders");

$total_revenue = adScalar($conn, "SELECT COALESCE(SUM(total), 0) FROM orders WHERE status != 'cancelled'");
if ($hasCustomOrders) {
    $total_revenue += adScalar($conn, "SELECT COALESCE(SUM(total_price), 0) FROM custom_orders WHERE status NOT IN ('cancelled')");
}

// 30-day vs previous 30-day windows drive every trend pill.
$WIN  = "created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
$PREV = "created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)";

$rev_now  = adScalar($conn, "SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'cancelled' AND $WIN");
$rev_prev = adScalar($conn, "SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'cancelled' AND $PREV");
if ($hasCustomOrders) {
    $rev_now  += adScalar($conn, "SELECT COALESCE(SUM(total_price),0) FROM custom_orders WHERE status NOT IN ('cancelled') AND $WIN");
    $rev_prev += adScalar($conn, "SELECT COALESCE(SUM(total_price),0) FROM custom_orders WHERE status NOT IN ('cancelled') AND $PREV");
}
[$rev_dir, $rev_pct] = adTrend($rev_now, $rev_prev);

$ord_now  = adScalar($conn, "SELECT COUNT(*) FROM orders WHERE $WIN");
$ord_prev = adScalar($conn, "SELECT COUNT(*) FROM orders WHERE $PREV");
[$ord_dir, $ord_pct] = adTrend($ord_now, $ord_prev);

$usr_now  = adScalar($conn, "SELECT COUNT(*) FROM users WHERE $WIN");
$usr_prev = adScalar($conn, "SELECT COUNT(*) FROM users WHERE $PREV");
[$usr_dir, $usr_pct] = adTrend($usr_now, $usr_prev);

$new_products = (int)adScalar($conn, "SELECT COUNT(*) FROM products WHERE status='active' AND $WIN");

// Sub-context lines
$pending_orders_count = (int)adScalar($conn, "SELECT COUNT(*) FROM orders WHERE status = 'pending'");
$low_stock_count = 0;
if (productsHasStockColumn()) {
    $low_stock_count = (int)adScalar($conn, "SELECT COUNT(*) FROM products WHERE status='active' AND stock <= 5");
}
$users_this_month = (int)adScalar($conn, "SELECT COUNT(*) FROM users WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");

// ---------------------------------------------------------------
// Sidebar badges (also consumed by includes/admin-sidebar.php)
// ---------------------------------------------------------------
$custom_designs_count = 0;
$pending_designs_count = 0;
if ($hasCustomDesigns) {
    $custom_designs_count  = (int)adScalar($conn, "SELECT COUNT(*) FROM custom_designs");
    $pending_designs_count = (int)adScalar($conn, "SELECT COUNT(*) FROM custom_designs WHERE status = 'pending'");
}
$custom_orders_count = 0;
$pending_custom_orders = 0;
if ($hasCustomOrders) {
    $custom_orders_count   = (int)adScalar($conn, "SELECT COUNT(*) FROM custom_orders");
    $pending_custom_orders = (int)adScalar($conn, "SELECT COUNT(*) FROM custom_orders WHERE status IN ('pending_payment','payment_uploaded')");
}

// ---------------------------------------------------------------
// Order status breakdown — the four statuses the card renders.
// ---------------------------------------------------------------
$status_counts = [];
$sb_q = $conn->query("SELECT status, COUNT(*) AS c FROM orders GROUP BY status");
if ($sb_q) {
    while ($r = $sb_q->fetch_assoc()) { $status_counts[$r['status']] = (int)$r['c']; }
}
$status_rows = [
    ['label' => 'Completed',       'key' => 'completed',        'colour' => 'c-green'],
    ['label' => 'Confirmed',       'key' => 'confirmed',        'colour' => 'c-amber'],
    ['label' => 'Pending',         'key' => 'pending',          'colour' => 'c-yellow'],
    ['label' => 'Out for delivery','key' => 'out_for_delivery', 'colour' => 'c-blue'],
];
$status_total = array_sum($status_counts);

// ---------------------------------------------------------------
// Recent orders + supporting lists
// ---------------------------------------------------------------
$recent_orders = $conn->query("SELECT o.*, u.fullname FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 6");

$top_products = [];
$tp_q = $conn->query("
    SELECT p.id, p.name, p.image,
           COALESCE(SUM(oi.quantity), 0) AS qty_sold,
           COALESCE(SUM(oi.quantity * oi.unit_price), 0) AS revenue
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    JOIN orders o   ON o.id = oi.order_id
    WHERE o.status != 'cancelled' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    GROUP BY p.id, p.name, p.image
    ORDER BY qty_sold DESC
    LIMIT 5
");
if ($tp_q) { while ($r = $tp_q->fetch_assoc()) { $top_products[] = $r; } }

$low_stock = [];
if (productsHasStockColumn()) {
    $ls_q = $conn->query("SELECT id, name, image, stock FROM products WHERE status='active' AND stock <= 5 ORDER BY stock ASC LIMIT 8");
    if ($ls_q) { while ($r = $ls_q->fetch_assoc()) { $low_stock[] = $r; } }
}

// ---------------------------------------------------------------
// Revenue chart — last 6 months, regular + custom orders combined
// ---------------------------------------------------------------
$revenue_data = [];
if ($hasCustomOrders) {
    $revenue_query = $conn->query("
        SELECT m.month, m.label,
               COALESCE(r.revenue, 0) + COALESCE(c.revenue, 0) as revenue,
               COALESCE(r.order_count, 0) + COALESCE(c.order_count, 0) as order_count
        FROM (
            SELECT DATE_FORMAT(created_at, '%Y-%m') as month, DATE_FORMAT(created_at, '%b') as label
            FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            UNION
            SELECT DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b')
            FROM custom_orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        ) m
        LEFT JOIN (
            SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total) as revenue, COUNT(*) as order_count
            FROM orders WHERE status != 'cancelled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ) r ON m.month = r.month
        LEFT JOIN (
            SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_price) as revenue, COUNT(*) as order_count
            FROM custom_orders WHERE status NOT IN ('cancelled') AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ) c ON m.month = c.month
        ORDER BY m.month ASC
    ");
} else {
    $revenue_query = $conn->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
               DATE_FORMAT(created_at, '%b') as label,
               COALESCE(SUM(total), 0) as revenue,
               COUNT(*) as order_count
        FROM orders
        WHERE status != 'cancelled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
}
if ($revenue_query) {
    while ($row = $revenue_query->fetch_assoc()) { $revenue_data[] = $row; }
}
$chart_labels  = json_encode(array_column($revenue_data, 'label'));
$chart_revenue = json_encode(array_map('floatval', array_column($revenue_data, 'revenue')));
$chart_orders  = json_encode(array_map('intval', array_column($revenue_data, 'order_count')));
$chart_range   = count($revenue_data)
    ? 'Monthly revenue, ' . $revenue_data[0]['label'] . '–' . end($revenue_data)['label']
    : 'Monthly revenue';

$adminFirstName = strtok(trim((string)($_SESSION['user_name'] ?? 'Admin')), ' ') ?: 'Admin';

/** Maps an order status onto one of the pill styles. */
function adStatusPill(string $status): string
{
    switch ($status) {
        case 'completed': return 's-completed';
        case 'confirmed': return 's-confirmed';
        case 'pending':   return 's-pending';
        default:          return 's-other';
    }
}
?>

<?php include '../includes/header/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="admin-container">

    <div class="ad-head">
        <div>
            <h1>Dashboard</h1>
            <p>Kumusta, <?php echo htmlspecialchars($adminFirstName); ?> — eto ang nangyayari sa store mo ngayon.</p>
        </div>
        <div class="ad-head-actions">
            <select class="ad-select" id="adRange" aria-label="Date range">
                <option value="6">Last 6 months</option>
                <option value="3">Last 3 months</option>
                <option value="12">Last 12 months</option>
            </select>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="ad-stats">
        <div class="ad-card">
            <div class="ad-stat-top">
                <span class="ad-stat-label">Total Revenue</span>
                <span class="ad-trend <?php echo $rev_dir; ?>"><?php echo htmlspecialchars($rev_pct); ?></span>
            </div>
            <div class="ad-stat-value">₱<?php echo number_format($total_revenue, 2); ?></div>
            <div class="ad-stat-sub">vs ₱<?php echo number_format($rev_prev, 2); ?> last period</div>
        </div>

        <div class="ad-card">
            <div class="ad-stat-top">
                <span class="ad-stat-label">Total Orders</span>
                <span class="ad-trend <?php echo $ord_dir; ?>"><?php echo htmlspecialchars($ord_pct); ?></span>
            </div>
            <div class="ad-stat-value"><?php echo number_format($orders_count); ?></div>
            <div class="ad-stat-sub"><?php echo number_format($pending_orders_count); ?> pending review</div>
        </div>

        <div class="ad-card">
            <div class="ad-stat-top">
                <span class="ad-stat-label">Products</span>
                <span class="ad-trend <?php echo $new_products > 0 ? 'up' : 'flat'; ?>"><?php echo $new_products > 0 ? '+' . $new_products . ' new' : 'No change'; ?></span>
            </div>
            <div class="ad-stat-value"><?php echo number_format($products_count); ?></div>
            <div class="ad-stat-sub"><?php echo number_format($low_stock_count); ?> low on stock</div>
        </div>

        <div class="ad-card">
            <div class="ad-stat-top">
                <span class="ad-stat-label">Total Users</span>
                <span class="ad-trend <?php echo $usr_dir; ?>"><?php echo htmlspecialchars($usr_pct); ?></span>
            </div>
            <div class="ad-stat-value"><?php echo number_format($users_count); ?></div>
            <div class="ad-stat-sub"><?php echo number_format($users_this_month); ?> joined this month</div>
        </div>
    </div>

    <!-- Revenue chart + order status -->
    <div class="ad-row">
        <div class="ad-card">
            <div class="ad-card-head">
                <div>
                    <h2>Revenue Trends</h2>
                    <p><?php echo htmlspecialchars($chart_range); ?></p>
                </div>
                <div class="ad-legend">
                    <span><i class="ad-legend-revenue" style="background:#17181b;"></i>Revenue</span>
                    <span><i style="background:#d9884c;"></i>Orders</span>
                </div>
            </div>
            <?php if (empty($revenue_data)): ?>
                <div class="ad-empty">No revenue in this period yet.</div>
            <?php else: ?>
                <div class="ad-chart-wrap"><canvas id="revenueChart"></canvas></div>
            <?php endif; ?>
        </div>

        <div class="ad-card">
            <div class="ad-card-head">
                <h2>Order Status</h2>
                <span style="font-size:12px;color:#97928a;white-space:nowrap;"><?php echo number_format($status_total); ?> total</span>
            </div>

            <?php if ($status_total === 0): ?>
                <div class="ad-empty">No orders yet.</div>
            <?php else: ?>
                <?php foreach ($status_rows as $row):
                    $count = $status_counts[$row['key']] ?? 0;
                    $pct   = $status_total > 0 ? round(($count / $status_total) * 100, 1) : 0;
                ?>
                <div class="ad-status-row">
                    <div class="ad-status-top">
                        <b><?php echo htmlspecialchars($row['label']); ?></b>
                        <span><?php echo number_format($count); ?></span>
                    </div>
                    <div class="ad-bar"><i class="<?php echo $row['colour']; ?>" style="width:<?php echo $pct; ?>%;"></i></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($pending_custom_orders > 0): ?>
            <div class="ad-alert">
                <div>
                    <small>Pending custom orders</small>
                    <b><?php echo number_format($pending_custom_orders); ?> to review</b>
                </div>
                <a href="custom-orders.php" class="ad-btn-dark">Review</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent orders -->
    <div class="ad-card" style="margin-bottom:18px;">
        <div class="ad-card-head">
            <h2>Recent Orders</h2>
            <a href="orders.php" class="ad-link">View all orders →</a>
        </div>
        <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>
        <div class="ad-table-wrap">
            <table class="ad-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($order = $recent_orders->fetch_assoc()): ?>
                    <tr>
                        <td class="ad-num">#<?php echo (int)$order['id']; ?></td>
                        <td><?php echo htmlspecialchars($order['fullname']); ?></td>
                        <td>₱<?php echo number_format($order['total'], 2); ?></td>
                        <td><span class="ad-pill <?php echo adStatusPill($order['status']); ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $order['status']))); ?></span></td>
                        <td><?php echo htmlspecialchars(paymentMethodLabel($order['payment_method'] ?? '')); ?></td>
                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                        <td style="text-align:right;"><a href="order_details.php?id=<?php echo (int)$order['id']; ?>" class="ad-btn-out">View</a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="ad-empty">No orders yet.</div>
        <?php endif; ?>
    </div>

    <!-- Top products + low stock -->
    <div class="ad-row">
        <div class="ad-card">
            <div class="ad-card-head">
                <div>
                    <h2>Top Selling Products</h2>
                    <p>Last 90 days</p>
                </div>
            </div>
            <?php if (empty($top_products)): ?>
                <div class="ad-empty">No sales data yet.</div>
            <?php else: ?>
            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead><tr><th>Product</th><th>Sold</th><th style="text-align:right;">Revenue</th></tr></thead>
                    <tbody>
                        <?php foreach ($top_products as $tp): ?>
                        <tr>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:10px;">
                                    <img src="../images/products/<?php echo htmlspecialchars($tp['image']); ?>" alt="" style="width:34px;height:34px;object-fit:cover;border-radius:8px;border:1px solid #e6e3dc;">
                                    <?php echo htmlspecialchars($tp['name']); ?>
                                </span>
                            </td>
                            <td><?php echo (int)$tp['qty_sold']; ?></td>
                            <td style="text-align:right;">₱<?php echo number_format($tp['revenue'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="ad-card">
            <div class="ad-card-head">
                <h2>Low Stock</h2>
                <a href="products.php" class="ad-link">Manage →</a>
            </div>
            <?php if (empty($low_stock)): ?>
                <div class="ad-empty">Everything is well stocked.</div>
            <?php else: ?>
                <?php foreach ($low_stock as $ls): ?>
                <div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid #f1eee8;">
                    <img src="../images/products/<?php echo htmlspecialchars($ls['image']); ?>" alt="" style="width:34px;height:34px;object-fit:cover;border-radius:8px;border:1px solid #e6e3dc;flex-shrink:0;">
                    <span style="flex:1 1 auto;min-width:0;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($ls['name']); ?></span>
                    <span class="ad-pill <?php echo (int)$ls['stock'] <= 0 ? 's-other' : 's-pending'; ?>"><?php echo (int)$ls['stock']; ?> left</span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="../js/admin-sidebar.js"></script>
<script>
(function () {
    const ctx = document.getElementById('revenueChart');
    if (!ctx || typeof Chart === 'undefined') return;

    const FONT = "'Instrument Sans', Helvetica, Arial, sans-serif";
    const peso = (v) => '₱' + (v >= 1000 ? (v / 1000).toFixed(1) + 'k' : Math.round(v));
    // Near-black bars/labels vanish on the dark-mode card, so both follow the theme.
    const isDark = () => document.documentElement.classList.contains('dark-mode');
    const barColor = () => isDark() ? '#e9e6e0' : '#17181b';
    const labelColor = () => isDark() ? '#b6b2aa' : '#6f6b63';

    // Draws the value above each revenue bar; Chart.js has no built-in for this
    // and the datalabels plugin is not loaded.
    const valueLabels = {
        id: 'valueLabels',
        afterDatasetsDraw(chart) {
            const meta = chart.getDatasetMeta(0);
            if (!meta || meta.hidden) return;
            const { ctx: c } = chart;
            c.save();
            c.font = '600 11px ' + FONT;
            c.fillStyle = labelColor();
            c.textAlign = 'center';
            c.textBaseline = 'bottom';
            meta.data.forEach((bar, i) => {
                const v = chart.data.datasets[0].data[i];
                if (!v) return;
                c.fillText(peso(v), bar.x, bar.y - 6);
            });
            c.restore();
        }
    };

    const chart = new Chart(ctx, {
        type: 'bar',
        plugins: [valueLabels],
        data: {
            labels: <?php echo $chart_labels; ?>,
            datasets: [
                {
                    label: 'Revenue',
                    data: <?php echo $chart_revenue; ?>,
                    backgroundColor: barColor(),
                    borderRadius: { topLeft: 6, topRight: 6, bottomLeft: 0, bottomRight: 0 },
                    borderSkipped: false,
                    barPercentage: 0.62,
                    categoryPercentage: 0.7
                },
                {
                    label: 'Orders',
                    data: <?php echo $chart_orders; ?>,
                    backgroundColor: '#d9884c',
                    borderRadius: { topLeft: 6, topRight: 6, bottomLeft: 0, bottomRight: 0 },
                    borderSkipped: false,
                    barPercentage: 0.62,
                    categoryPercentage: 0.7,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { top: 22 } },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },   // rendered as HTML in the card head
                tooltip: {
                    backgroundColor: '#17181b',
                    padding: 11,
                    cornerRadius: 9,
                    titleFont: { family: FONT, size: 12 },
                    bodyFont: { family: FONT, size: 12 },
                    callbacks: {
                        label: (c) => c.dataset.label === 'Revenue'
                            ? '₱' + c.parsed.y.toLocaleString(undefined, { minimumFractionDigits: 2 })
                            : c.parsed.y + ' orders'
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: { font: { family: FONT, size: 12 }, color: labelColor() }
                },
                y:  { display: false, beginAtZero: true },
                y1: { display: false, beginAtZero: true }
            }
        }
    });

    // Recolour in place when the theme toggle flips html.dark-mode.
    new MutationObserver(() => {
        chart.data.datasets[0].backgroundColor = barColor();
        chart.options.scales.x.ticks.color = labelColor();
        chart.update('none');
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
})();
</script>

<?php include '../includes/footer/footer.php'; ?>
