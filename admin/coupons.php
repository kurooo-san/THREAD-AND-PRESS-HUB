<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Every POST form on this page carries csrf_token (csrfTokenField()); refuse anything else.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrfToken()) {
    http_response_code(403);
    exit('Your session expired or the form was invalid. Go back, refresh the page and try again.');
}

$pageTitle = 'Coupons';
$tableExists = couponsTableExists();

// <input type="datetime-local"> value -> MySQL DATETIME, or null when blank/invalid.
function couponDateIn($value) {
    $value = trim((string)$value);
    if ($value === '') return null;
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    return $d ? $d->format('Y-m-d H:i:s') : false;
}

if ($tableExists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'update') {
        $editId      = $action === 'update' ? (int)($_POST['id'] ?? 0) : 0;
        $code        = strtoupper(trim($_POST['code'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $type        = ($_POST['discount_type'] ?? '') === 'fixed' ? 'fixed' : 'percent';
        $value       = (float)($_POST['discount_value'] ?? 0);
        $minSubtotal = max(0, (float)($_POST['min_subtotal'] ?? 0));
        $maxUses     = trim($_POST['max_uses'] ?? '') === '' ? null : (int)$_POST['max_uses'];
        $validFrom   = couponDateIn($_POST['valid_from'] ?? '');
        $validUntil  = couponDateIn($_POST['valid_until'] ?? '');
        $isActive    = !empty($_POST['is_active']) ? 1 : 0;

        // The code is fixed once created: orders store it, and the "Given"
        // totals below are matched by code.
        if ($editId) {
            $stmt = $conn->prepare("SELECT code FROM coupons WHERE id = ?");
            $stmt->bind_param("i", $editId);
            $stmt->execute();
            $code = $stmt->get_result()->fetch_row()[0] ?? null;
            $stmt->close();
        }

        if ($editId && $code === null) {
            $errorMsg = 'That coupon no longer exists.';
            $editId = 0;
        } elseif (!preg_match('/^[A-Z0-9_-]{3,50}$/', $code)) {
            $errorMsg = 'Code must be 3–50 characters: letters, numbers, dash or underscore.';
        } elseif ($value <= 0 || ($type === 'percent' && $value > 100)) {
            $errorMsg = $type === 'percent' ? 'Percent discount must be between 1 and 100.' : 'Fixed discount must be more than ₱0.';
        } elseif ($maxUses !== null && $maxUses < 1) {
            $errorMsg = 'Max uses must be at least 1, or leave it blank for unlimited.';
        } elseif ($validFrom === false || $validUntil === false) {
            $errorMsg = 'Invalid date.';
        } elseif ($validFrom && $validUntil && $validUntil <= $validFrom) {
            $errorMsg = '"Valid until" must be after "Valid from".';
        } else {
            $description = $description === '' ? null : mb_substr($description, 0, 255);
            if ($editId) {
                $stmt = $conn->prepare("UPDATE coupons SET description = ?, discount_type = ?, discount_value = ?, min_subtotal = ?,
                                               max_uses = ?, valid_from = ?, valid_until = ?, is_active = ?
                                        WHERE id = ?");
                $stmt->bind_param("ssddissii", $description, $type, $value, $minSubtotal, $maxUses, $validFrom, $validUntil, $isActive, $editId);
            } else {
                $stmt = $conn->prepare("INSERT INTO coupons (code, description, discount_type, discount_value, min_subtotal, max_uses, valid_from, valid_until, is_active)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssddissi", $code, $description, $type, $value, $minSubtotal, $maxUses, $validFrom, $validUntil, $isActive);
            }
            // Works whether mysqli throws (PHP 8.1+ default) or just returns false.
            try {
                $saved = $stmt->execute();
                $errNo = $stmt->errno;
                $errText = $stmt->error;
            } catch (mysqli_sql_exception $e) {
                $saved = false;
                $errNo = $e->getCode();
                $errText = $e->getMessage();
            }
            if ($saved && $editId) {
                logAudit('coupon_update', 'coupon', $editId, $code);
                $successMsg = "Coupon $code updated.";
                $editId = 0;
            } elseif ($saved) {
                logAudit('coupon_create', 'coupon', $conn->insert_id, $code);
                $successMsg = "Coupon $code created.";
            } elseif ($errNo === 1062) {
                $errorMsg = "Coupon code $code already exists.";
            } else {
                error_log('[coupons] ' . $errText);
                $errorMsg = 'Could not save the coupon.';
            }
            $stmt->close();
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE coupons SET is_active = 1 - is_active WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        logAudit('coupon_toggle', 'coupon', $id);
        $successMsg = 'Coupon status updated.';
    } elseif ($action === 'delete') {
        // Orders keep their own copy of coupon_code / coupon_discount, so
        // deleting a coupon never changes a past order's totals.
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM coupons WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        logAudit('coupon_delete', 'coupon', $id);
        $successMsg = 'Coupon deleted.';
    }
}

$coupons = $tableExists
    ? $conn->query("SELECT * FROM coupons ORDER BY created_at DESC, id DESC")->fetch_all(MYSQLI_ASSOC)
    : [];

// Total discount actually given per code, from the orders themselves.
$givenByCode = [];
$cc = $conn->query("SHOW COLUMNS FROM orders LIKE 'coupon_code'");
if ($cc && $cc->num_rows > 0) {
    $r = $conn->query("SELECT coupon_code, SUM(coupon_discount) AS given FROM orders
                       WHERE coupon_code IS NOT NULL AND status <> 'cancelled' GROUP BY coupon_code");
    foreach ($r->fetch_all(MYSQLI_ASSOC) as $row) {
        $givenByCode[$row['coupon_code']] = (float)$row['given'];
    }
}

function couponStatus(array $c) {
    if ((int)$c['is_active'] !== 1)                                          return ['Inactive', 'secondary'];
    if ($c['valid_until'] && strtotime($c['valid_until']) < time())          return ['Expired', 'danger'];
    if ($c['max_uses'] !== null && (int)$c['times_used'] >= (int)$c['max_uses']) return ['Used up', 'warning'];
    if ($c['valid_from'] && strtotime($c['valid_from']) > time())            return ['Scheduled', 'info'];
    return ['Active', 'success'];
}

$liveCount = count(array_filter($coupons, fn($c) => couponStatus($c)[0] === 'Active'));

// Form contents: what was just posted if it failed, else the coupon opened
// with ?edit=ID, else blank for a new coupon.
$editId = $editId ?? 0;
$keep = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($errorMsg)) {
    $keep = $_POST;
    if ($editId) $keep['code'] = $code;
} elseif (isset($_GET['edit'])) {
    foreach ($coupons as $c) {
        if ((int)$c['id'] === (int)$_GET['edit']) {
            $editId = (int)$c['id'];
            $keep = $c;
            foreach (['valid_from', 'valid_until'] as $f) {
                $keep[$f] = $c[$f] ? date('Y-m-d\TH:i', strtotime($c[$f])) : '';
            }
            $keep['discount_value'] = rtrim(rtrim($c['discount_value'], '0'), '.');
            $keep['min_subtotal']   = rtrim(rtrim($c['min_subtotal'], '0'), '.');
        }
    }
}
?>

<?php include '../includes/header/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="admin-container">
    <div class="mb-4">
        <h1 class="text-coffee-dark mb-2" style="font-size: 2rem; font-weight: 800;">
            <i class="fas fa-ticket-alt"></i> Coupons
        </h1>
        <p class="text-muted">Create discount codes customers can apply at checkout.</p>
    </div>

    <?php if (!$tableExists): ?>
        <div class="alert alert-warning">Coupons table not found. Run the migration first.</div>
    <?php else: ?>

    <?php if (isset($successMsg)): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?></div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="admin-card text-center">
                <h5 class="mb-2"><?php echo count($coupons); ?></h5>
                <p class="mb-0 text-muted">Total coupons</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-card text-center">
                <h5 class="mb-2"><?php echo $liveCount; ?></h5>
                <p class="mb-0 text-muted">Usable right now</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-card text-center">
                <h5 class="mb-2">₱<?php echo number_format(array_sum($givenByCode), 2); ?></h5>
                <p class="mb-0 text-muted">Total discount given</p>
            </div>
        </div>
    </div>

    <div class="admin-card mb-4">
        <h5 class="mb-3" id="couponForm">
            <?php if ($editId): ?>
                <i class="fas fa-edit"></i> Edit coupon <?php echo htmlspecialchars($keep['code'] ?? ''); ?>
            <?php else: ?>
                <i class="fas fa-plus"></i> New coupon
            <?php endif; ?>
        </h5>
        <form method="POST" action="coupons.php"><?php echo csrfTokenField(); ?>
            <input type="hidden" name="action" value="<?php echo $editId ? 'update' : 'add'; ?>">
            <input type="hidden" name="id" value="<?php echo (int)$editId; ?>">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold" for="cpCode">Code *</label>
                    <input type="text" class="form-control" id="cpCode" name="code" required maxlength="50"
                           pattern="[A-Za-z0-9_\-]{3,50}" placeholder="e.g. WELCOME10" style="text-transform: uppercase;"
                           value="<?php echo htmlspecialchars($keep['code'] ?? ''); ?>"
                           <?php echo $editId ? 'readonly title="The code is fixed once created."' : ''; ?>>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-bold" for="cpDesc">Description</label>
                    <input type="text" class="form-control" id="cpDesc" name="description" maxlength="255"
                           placeholder="e.g. 10% off for new customers"
                           value="<?php echo htmlspecialchars($keep['description'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold" for="cpType">Type *</label>
                    <select class="form-select" id="cpType" name="discount_type">
                        <option value="percent">Percent (%)</option>
                        <option value="fixed" <?php echo ($keep['discount_type'] ?? '') === 'fixed' ? 'selected' : ''; ?>>Fixed (₱)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold" for="cpValue">Value *</label>
                    <input type="number" class="form-control" id="cpValue" name="discount_value" required min="0.01" step="0.01"
                           value="<?php echo htmlspecialchars($keep['discount_value'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold" for="cpMin">Minimum subtotal (₱)</label>
                    <input type="number" class="form-control" id="cpMin" name="min_subtotal" min="0" step="0.01"
                           value="<?php echo htmlspecialchars($keep['min_subtotal'] ?? '0'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold" for="cpMax">Max uses</label>
                    <input type="number" class="form-control" id="cpMax" name="max_uses" min="1" step="1" placeholder="Unlimited"
                           value="<?php echo htmlspecialchars($keep['max_uses'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold" for="cpFrom">Valid from</label>
                    <input type="datetime-local" class="form-control" id="cpFrom" name="valid_from"
                           value="<?php echo htmlspecialchars($keep['valid_from'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold" for="cpUntil">Valid until</label>
                    <input type="datetime-local" class="form-control" id="cpUntil" name="valid_until"
                           value="<?php echo htmlspecialchars($keep['valid_until'] ?? ''); ?>">
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="cpActive" value="1"
                           <?php echo (!$keep || !empty($keep['is_active'])) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="cpActive">Active</label>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($editId): ?>
                    <a href="coupons.php" class="btn btn-outline-secondary">Cancel</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $editId ? 'Save changes' : 'Create coupon'; ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="admin-card">
        <h5 class="mb-3"><i class="fas fa-list"></i> All coupons</h5>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Discount</th>
                        <th>Min. subtotal</th>
                        <th>Used</th>
                        <th>Given</th>
                        <th>Valid</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($coupons)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No coupons yet. Create one above.</td></tr>
                    <?php else: ?>
                        <?php foreach ($coupons as $c): [$label, $color] = couponStatus($c); ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($c['code']); ?></strong>
                                <?php if ($c['description']): ?>
                                <br><span class="text-muted small"><?php echo htmlspecialchars($c['description']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $c['discount_type'] === 'percent'
                                    ? rtrim(rtrim(number_format((float)$c['discount_value'], 2), '0'), '.') . '% off'
                                    : '₱' . number_format((float)$c['discount_value'], 2) . ' off'; ?>
                            </td>
                            <td><?php echo (float)$c['min_subtotal'] > 0 ? '₱' . number_format((float)$c['min_subtotal'], 2) : '—'; ?></td>
                            <td><?php echo (int)$c['times_used']; ?> / <?php echo $c['max_uses'] === null ? '∞' : (int)$c['max_uses']; ?></td>
                            <td>₱<?php echo number_format($givenByCode[$c['code']] ?? 0, 2); ?></td>
                            <td class="small">
                                <?php echo $c['valid_from'] ? date('M d, Y H:i', strtotime($c['valid_from'])) : 'Anytime'; ?>
                                <br>→ <?php echo $c['valid_until'] ? date('M d, Y H:i', strtotime($c['valid_until'])) : 'No expiry'; ?>
                            </td>
                            <td><span class="badge bg-<?php echo $color; ?>"><?php echo $label; ?></span></td>
                            <td class="text-end text-nowrap">
                                <a href="coupons.php?edit=<?php echo (int)$c['id']; ?>#couponForm" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <form method="POST" action="coupons.php" style="display:inline;"><?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        <?php echo (int)$c['is_active'] === 1 ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                <form method="POST" action="coupons.php" style="display:inline;" onsubmit="return confirm('Delete coupon <?php echo htmlspecialchars($c['code'], ENT_QUOTES); ?>? Past orders keep their discount.');"><?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
