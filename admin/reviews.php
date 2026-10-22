<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reviews.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Every POST form on this page carries csrf_token (csrfTokenField()); refuse anything else.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrfToken()) {
    http_response_code(403);
    exit('Your session expired or the form was invalid. Go back, refresh the page and try again.');
}

$pageTitle   = 'Product Reviews';
$tableExists = reviewsTableExists();

// Filters, kept on every link and form so Hide/Unhide returns to the same view.
$fStatus = in_array($_GET['status'] ?? '', ['published', 'hidden'], true) ? $_GET['status'] : '';
$fRating = in_array((int)($_GET['rating'] ?? 0), [1, 2, 3, 4, 5], true) ? (int)$_GET['rating'] : 0;
$fQuery  = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);

// ?product=ID turns this into that product's own review page. Any status,
// so reviews of an inactive product can still be moderated.
$fProduct = max(0, (int)($_GET['product'] ?? 0));
$product  = null;
if ($tableExists && $fProduct) {
    $stmt = $conn->prepare("SELECT id, name, image, price, status FROM products WHERE id = ?");
    $stmt->bind_param("i", $fProduct);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
// Reviews cascade-delete with their product, so a missing product has nothing to show.
if (!$product) { $fProduct = 0; }

$backUrl = 'reviews.php' . (($qs = http_build_query(array_filter(['product' => $fProduct ?: null, 'status' => $fStatus, 'rating' => $fRating ?: null, 'q' => $fQuery]))) ? '?' . $qs : '');
$baseUrl = $fProduct ? 'reviews.php?product=' . $fProduct : 'reviews.php'; // "Clear" keeps the product

// Hide / unhide: sets an explicit status (not a toggle), so a resubmit or
// double click cannot flip it back. Hidden reviews drop out of the product
// page and its star average; the customer editing it later does not unhide it.
if ($tableExists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $status = ['hide' => 'hidden', 'unhide' => 'published'][$action] ?? null;

    if ($status === null || $id <= 0) {
        $errorMsg = 'Unknown action.';
    } else {
        $stmt = $conn->prepare("UPDATE product_reviews SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();

        if ($changed > 0) {
            logAudit('review_' . $action, 'review', $id);
            $successMsg = $status === 'hidden'
                ? 'Review hidden. It no longer shows on the product page or counts toward its rating.'
                : 'Review is visible on the product page again.';
        } else {
            // 0 rows: already in that state (double click) or deleted meanwhile.
            $exists = $conn->query("SELECT 1 FROM product_reviews WHERE id = " . $id)->num_rows > 0;
            if ($exists) {
                $successMsg = $status === 'hidden' ? 'Review was already hidden.' : 'Review was already visible.';
            } else {
                $errorMsg = 'That review no longer exists.';
            }
        }
    }
}

$reviews = [];
$stats   = ['total' => 0, 'published' => 0, 'hidden' => 0, 'avg' => 0.0];
if ($tableExists) {
    // $fProduct is an int from the products table, safe to interpolate.
    $s = $conn->query("SELECT COUNT(*) AS total, SUM(status = 'published') AS published, SUM(status = 'hidden') AS hidden,
                              AVG(CASE WHEN status = 'published' THEN rating END) AS avg
                       FROM product_reviews" . ($fProduct ? " WHERE product_id = $fProduct" : ''))->fetch_assoc();
    $stats = ['total' => (int)$s['total'], 'published' => (int)$s['published'], 'hidden' => (int)$s['hidden'], 'avg' => (float)$s['avg']];

    // Overview: one row per reviewed product, each linking to its own page.
    $reviewedProducts = $fProduct ? [] : $conn->query(
        "SELECT p.id, p.name, p.image, COUNT(*) AS total, SUM(r.status = 'published') AS published,
                SUM(r.status = 'hidden') AS hidden, AVG(CASE WHEN r.status = 'published' THEN r.rating END) AS avg,
                MAX(r.created_at) AS latest
         FROM product_reviews r JOIN products p ON p.id = r.product_id
         GROUP BY p.id, p.name, p.image
         ORDER BY latest DESC")->fetch_all(MYSQLI_ASSOC);
    $dist = $fProduct ? reviewDistribution($fProduct) : [];

    $where = [];
    $types = '';
    $args  = [];
    if ($fProduct)       { $where[] = 'r.product_id = ?'; $types .= 'i'; $args[] = $fProduct; }
    if ($fStatus !== '') { $where[] = 'r.status = ?'; $types .= 's'; $args[] = $fStatus; }
    if ($fRating)        { $where[] = 'r.rating = ?'; $types .= 'i'; $args[] = $fRating; }
    if ($fQuery !== '') {
        $where[] = '(p.name LIKE ? OR u.fullname LIKE ? OR r.title LIKE ? OR r.body LIKE ?)';
        $like = '%' . addcslashes($fQuery, '%_\\') . '%';
        $types .= 'ssss';
        array_push($args, $like, $like, $like, $like);
    }
    // LEFT JOINs: both foreign keys cascade, but never let a missing row hide a review from the admin.
    $sql = "SELECT r.*, p.name AS product_name, u.fullname, u.email
            FROM product_reviews r
            LEFT JOIN products p ON p.id = r.product_id
            LEFT JOIN users u    ON u.id = r.user_id"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . " ORDER BY r.created_at DESC, r.id DESC";
    $stmt = $conn->prepare($sql);
    if ($args) { $stmt->bind_param($types, ...$args); }
    $stmt->execute();
    $reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<?php include '../includes/header/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="admin-container">
    <div class="mb-4">
        <?php if ($product): ?>
        <a href="reviews.php" class="small text-decoration-none"><i class="fas fa-arrow-left"></i> All reviews</a>
        <?php endif; ?>
        <h1 class="text-coffee-dark mb-2" style="font-size: 2rem; font-weight: 800;">
            <i class="fas fa-star"></i> <?php echo $product ? 'Reviews: ' . htmlspecialchars($product['name']) : 'Product Reviews'; ?>
        </h1>
        <p class="text-muted">Every review customers have left<?php echo $product ? ' on this product' : ''; ?>. Hide spam or abusive reviews; hidden ones disappear from the product page and its star rating, and can be shown again anytime.</p>
    </div>

    <?php if (!$tableExists): ?>
        <div class="alert alert-warning">Reviews table not found. Run migrate_product_reviews.sql first.</div>
    <?php else: ?>

    <?php if (isset($successMsg)): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?></div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="admin-card text-center">
            <h5 class="mb-2"><?php echo $stats['total']; ?></h5><p class="mb-0 text-muted">Total reviews</p>
        </div></div>
        <div class="col-6 col-md-3"><div class="admin-card text-center">
            <h5 class="mb-2"><?php echo $stats['published']; ?></h5><p class="mb-0 text-muted">Visible</p>
        </div></div>
        <div class="col-6 col-md-3"><div class="admin-card text-center">
            <h5 class="mb-2"><?php echo $stats['hidden']; ?></h5><p class="mb-0 text-muted">Hidden</p>
        </div></div>
        <div class="col-6 col-md-3"><div class="admin-card text-center">
            <h5 class="mb-2"><?php echo $stats['published'] ? number_format($stats['avg'], 2) . ' / 5' : '—'; ?></h5><p class="mb-0 text-muted">Average rating (visible)</p>
        </div></div>
    </div>

    <?php if ($product): ?>
    <div class="admin-card mb-4">
        <div class="d-flex gap-4 align-items-center flex-wrap">
            <img src="../images/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>"
                 style="width: 110px; height: 110px; object-fit: cover; border-radius: 10px; background: #f0f0f0;"
                 onerror="this.onerror=null;this.style.visibility='hidden';">
            <div style="flex: 1 1 220px;">
                <h5 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h5>
                <div class="text-muted small mb-2">
                    ₱<?php echo number_format((float)$product['price'], 2); ?> ·
                    <span class="badge bg-<?php echo $product['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars(ucfirst($product['status'])); ?></span>
                </div>
                <?php if ($product['status'] === 'active'): ?>
                <a href="../product.php?id=<?php echo (int)$product['id']; ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark"><i class="fas fa-external-link-alt"></i> View in store</a>
                <?php else: ?>
                <span class="text-muted small">Not in the store while inactive.</span>
                <?php endif; ?>
            </div>
            <div style="flex: 1 1 240px;" aria-label="Rating breakdown of visible reviews">
                <?php foreach ($dist as $star => $c): $pct = $stats['published'] ? round($c / $stats['published'] * 100) : 0; ?>
                <div class="d-flex align-items-center gap-2 small mb-1">
                    <span class="text-nowrap" style="width: 2.5rem;"><?php echo $star; ?> ★</span>
                    <div class="flex-grow-1" style="height: 8px; background: rgba(127,127,127,.2); border-radius: 4px; overflow: hidden;">
                        <div style="width: <?php echo $pct; ?>%; height: 100%; background: #e8a05a;"></div>
                    </div>
                    <span class="text-muted text-end" style="width: 2rem;"><?php echo $c; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php elseif ($reviewedProducts): ?>
    <div class="admin-card mb-4">
        <h5 class="mb-3"><i class="fas fa-tshirt"></i> Reviewed products (<?php echo count($reviewedProducts); ?>)</h5>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Product</th><th class="text-end">Reviews</th><th class="text-end">Hidden</th><th class="text-end">Average</th><th>Latest review</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($reviewedProducts as $p): ?>
                    <tr>
                        <td><a href="reviews.php?product=<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></a></td>
                        <td class="text-end"><?php echo (int)$p['total']; ?></td>
                        <td class="text-end"><?php echo (int)$p['hidden']; ?></td>
                        <td class="text-end"><?php echo (int)$p['published'] ? number_format((float)$p['avg'], 2) . ' ★' : '—'; ?></td>
                        <td class="small"><?php echo date('M d, Y', strtotime($p['latest'])); ?></td>
                        <td class="text-end"><a href="reviews.php?product=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-dark">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="admin-card mb-4">
        <form method="GET" action="reviews.php" class="d-flex gap-3 align-items-end flex-wrap">
            <?php if ($fProduct): ?><input type="hidden" name="product" value="<?php echo $fProduct; ?>"><?php endif; ?>
            <div>
                <label class="form-label small fw-bold" for="rvStatus">Status</label>
                <select class="form-select form-select-sm" id="rvStatus" name="status">
                    <option value="">All</option>
                    <option value="published" <?php echo $fStatus === 'published' ? 'selected' : ''; ?>>Visible</option>
                    <option value="hidden" <?php echo $fStatus === 'hidden' ? 'selected' : ''; ?>>Hidden</option>
                </select>
            </div>
            <div>
                <label class="form-label small fw-bold" for="rvRating">Rating</label>
                <select class="form-select form-select-sm" id="rvRating" name="rating">
                    <option value="">Any</option>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?php echo $i; ?>" <?php echo $fRating === $i ? 'selected' : ''; ?>><?php echo $i; ?> star<?php echo $i > 1 ? 's' : ''; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="flex-grow-1" style="min-width: 200px;">
                <label class="form-label small fw-bold" for="rvQ">Search</label>
                <input type="search" class="form-control form-control-sm" id="rvQ" name="q" maxlength="100"
                       placeholder="Product, customer or review text" value="<?php echo htmlspecialchars($fQuery); ?>">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-dark">Filter</button>
                <?php if ($backUrl !== $baseUrl): ?>
                <a href="<?php echo $baseUrl; ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="admin-card">
        <h5 class="mb-3"><i class="fas fa-list"></i> Reviews (<?php echo count($reviews); ?>)</h5>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Customer</th>
                        <th>Rating</th>
                        <th style="min-width: 260px;">Review</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$reviews): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">
                        <?php echo $stats['total'] ? 'No reviews match these filters.' : 'No reviews yet.'; ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($reviews as $rv): $hidden = $rv['status'] === 'hidden'; ?>
                    <tr<?php echo $hidden ? ' class="text-muted"' : ''; ?>>
                        <td class="small text-nowrap"><?php echo date('M d, Y H:i', strtotime($rv['created_at'])); ?></td>
                        <td>
                            <?php if ($rv['product_name'] !== null): ?>
                            <a href="reviews.php?product=<?php echo (int)$rv['product_id']; ?>"><?php echo htmlspecialchars($rv['product_name']); ?></a>
                            <?php else: ?>
                            <span class="text-muted">(deleted product)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($rv['fullname'] ?? '(deleted user)'); ?>
                            <?php if ((int)$rv['is_verified'] === 1): ?><br><span class="badge bg-success">Verified buyer</span><?php endif; ?>
                        </td>
                        <?php $stars = max(1, min(5, (int)$rv['rating'])); // renderStars() needs product-modern.css, which admin pages don't load ?>
                        <td class="text-nowrap" style="color:#e8a05a;" aria-label="<?php echo $stars; ?> out of 5 stars"><?php echo str_repeat('★', $stars) . str_repeat('☆', 5 - $stars); ?></td>
                        <td class="small">
                            <?php if (trim((string)$rv['title']) !== ''): ?><strong><?php echo htmlspecialchars($rv['title']); ?></strong><br><?php endif; ?>
                            <?php echo trim((string)$rv['body']) !== '' ? nl2br(htmlspecialchars($rv['body'])) : '<span class="text-muted">(no comment)</span>'; ?>
                        </td>
                        <td><span class="badge bg-<?php echo $hidden ? 'secondary' : 'success'; ?>"><?php echo $hidden ? 'Hidden' : 'Visible'; ?></span></td>
                        <td class="text-end text-nowrap">
                            <form method="POST" action="<?php echo htmlspecialchars($backUrl); ?>" style="display:inline;"
                                  <?php if (!$hidden): ?>onsubmit="return confirm('Hide this review from the product page?');"<?php endif; ?>><?php echo csrfTokenField(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$rv['id']; ?>">
                                <?php if ($hidden): ?>
                                <button type="submit" name="action" value="unhide" class="btn btn-sm btn-outline-success"><i class="fas fa-eye"></i> Unhide</button>
                                <?php else: ?>
                                <button type="submit" name="action" value="hide" class="btn btn-sm btn-outline-danger"><i class="fas fa-eye-slash"></i> Hide</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->
<script src="../js/admin-sidebar.js"></script>

<?php include '../includes/footer/footer.php'; ?>
