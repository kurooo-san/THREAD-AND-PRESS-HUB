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
// vouchersReady() also adds the `kind` column and the wallet table on first visit.
$tableExists = couponsTableExists() && vouchersReady();

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
        $kind        = ($_POST['kind'] ?? '') === 'shipping' ? 'shipping' : 'discount';
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
                $stmt = $conn->prepare("UPDATE coupons SET description = ?, kind = ?, discount_type = ?, discount_value = ?, min_subtotal = ?,
                                               max_uses = ?, valid_from = ?, valid_until = ?, is_active = ?
                                        WHERE id = ?");
                $stmt->bind_param("sssddissii", $description, $kind, $type, $value, $minSubtotal, $maxUses, $validFrom, $validUntil, $isActive, $editId);
            } else {
                $stmt = $conn->prepare("INSERT INTO coupons (code, description, kind, discount_type, discount_value, min_subtotal, max_uses, valid_from, valid_until, is_active)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssddissi", $code, $description, $kind, $type, $value, $minSubtotal, $maxUses, $validFrom, $validUntil, $isActive);
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
        // ...and it leaves every customer's wallet with it.
        $stmt = $conn->prepare("DELETE FROM user_coupons WHERE coupon_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        logAudit('coupon_delete', 'coupon', $id);
        $successMsg = 'Coupon deleted.';
    } elseif ($action === 'give') {
        $giveId  = (int)($_POST['id'] ?? 0);
        $toAll   = ($_POST['recipients'] ?? '') === 'all';
        $picked  = array_filter(array_map('intval', (array)($_POST['user_ids'] ?? [])));
        $note    = (string)($_POST['note'] ?? '');
        $stmt = $conn->prepare("SELECT * FROM coupons WHERE id = ?");
        $stmt->bind_param("i", $giveId);
        $stmt->execute();
        $giveCoupon = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$giveCoupon) {
            $errorMsg = 'That coupon no longer exists.';
        } elseif (!$toAll && !$picked) {
            $errorMsg = 'Pick at least one customer, or choose "All customers".';
        } else {
            $given = voucherGive($giveId, $toAll ? null : $picked, $note, (int)$_SESSION['user_id']);

            // One email per new holder. ponytail: sent inline, fine for a
            // capstone-sized customer list; move to a queue past a few hundred.
            set_time_limit(300);
            require_once __DIR__ . '/../includes/email-helper.php';
            $mailed = 0;
            if ($given) {
                $in = implode(',', array_map('intval', $given));
                foreach ($conn->query("SELECT fullname, email FROM users WHERE id IN ($in)")->fetch_all(MYSQLI_ASSOC) as $u) {
                    try {
                        if (sendVoucherGiftEmail($u['email'], $u['fullname'], $giveCoupon, $note)) $mailed++;
                    } catch (Throwable $e) {
                        error_log('[coupons] gift email: ' . $e->getMessage());
                    }
                }
            }
            logAudit('coupon_give', 'coupon', $giveId, $giveCoupon['code'] . ' x' . count($given));
            $successMsg = $given
                ? 'Gave ' . $giveCoupon['code'] . ' to ' . count($given) . ' customer(s); ' . $mailed . ' email(s) sent.'
                : 'Everyone you picked already has ' . $giveCoupon['code'] . '.';
        }
        if (isset($errorMsg)) $_GET['give'] = $giveId; // keep the give form open
    }
}

// Activate/Deactivate from a <form data-ajax>: answer with JSON, not the page.
if ($tableExists && $_SERVER['REQUEST_METHOD'] === 'POST' && isAjaxForm()) {
    ajaxFormReply(!isset($errorMsg), $errorMsg ?? $successMsg ?? 'Saved.');
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

$liveCount = count(array_filter($coupons, fn($c) => couponStatus($c)[0] === 'Active'));

// How many customers hold each coupon, and how many already spent it.
$heldBy = [];
if ($tableExists) {
    foreach ($conn->query("SELECT coupon_id, COUNT(*) AS held, SUM(used_at IS NOT NULL) AS spent
                             FROM user_coupons GROUP BY coupon_id")->fetch_all(MYSQLI_ASSOC) as $row) {
        $heldBy[(int)$row['coupon_id']] = $row;
    }
}

// "Give" panel, opened with ?give=ID: every customer, flagged if they already hold it.
$giveCoupon = null;
$customers  = [];
if ($tableExists && isset($_GET['give'])) {
    foreach ($coupons as $c) {
        if ((int)$c['id'] === (int)$_GET['give']) $giveCoupon = $c;
    }
    if ($giveCoupon) {
        $stmt = $conn->prepare("SELECT u.id, u.fullname, u.email, (uc.id IS NOT NULL) AS has_it
                                  FROM users u
                                  LEFT JOIN user_coupons uc ON uc.user_id = u.id AND uc.coupon_id = ?
                                 WHERE u.user_type <> 'admin'
                                 ORDER BY u.fullname");
        $gid = (int)$giveCoupon['id'];
        $stmt->bind_param("i", $gid);
        $stmt->execute();
        $customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Form contents: what was just posted if it failed, else the coupon opened
// with ?edit=ID, else blank for a new coupon.
$editId = $editId ?? 0;
$keep = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($errorMsg) && in_array($_POST['action'] ?? '', ['add', 'update'], true)) {
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
        <p class="text-muted">Create vouchers, then give them to customers. Customers pick from their vouchers at checkout; there is no code to type.</p>
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

    <div class="row mb-4" data-live="coupon-stats">
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

    <?php if ($giveCoupon): ?>
    <div class="admin-card mb-4" id="givePanel" style="border: 2px solid #16a34a;">
        <h5 class="mb-1"><i class="fas fa-gift"></i> Give <?php echo htmlspecialchars($giveCoupon['code']); ?>
            <span class="badge bg-dark ms-1"><?php echo htmlspecialchars(voucherLabel($giveCoupon)); ?></span></h5>
        <p class="text-muted small mb-3">It lands in each customer's My Vouchers and they get an email. Customers who already have it are skipped.</p>
        <form method="POST" action="coupons.php"><?php echo csrfTokenField(); ?>
            <input type="hidden" name="action" value="give">
            <input type="hidden" name="id" value="<?php echo (int)$giveCoupon['id']; ?>">

            <div class="d-flex gap-3 mb-2 flex-wrap">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="recipients" id="giveSome" value="some" checked>
                    <label class="form-check-label" for="giveSome">Selected customers</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="recipients" id="giveAll" value="all">
                    <label class="form-check-label" for="giveAll">All customers (<?php echo count($customers); ?>)</label>
                </div>
            </div>

            <div id="givePickList">
                <input type="search" class="form-control form-control-sm mb-2" id="giveFilter" placeholder="Search name or email" aria-label="Search customers">
                <div style="max-height: 260px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 6px; padding: 0.5rem;">
                    <?php if (!$customers): ?>
                        <p class="text-muted small mb-0">No customer accounts yet.</p>
                    <?php endif; ?>
                    <?php foreach ($customers as $u): $uid = (int)$u['id']; ?>
                    <div class="form-check give-row" data-search="<?php echo htmlspecialchars(mb_strtolower($u['fullname'] . ' ' . $u['email'])); ?>">
                        <input class="form-check-input" type="checkbox" name="user_ids[]" value="<?php echo $uid; ?>" id="giveU<?php echo $uid; ?>"
                               <?php echo $u['has_it'] ? 'disabled' : ''; ?>>
                        <label class="form-check-label small" for="giveU<?php echo $uid; ?>">
                            <?php echo htmlspecialchars($u['fullname']); ?> <span class="text-muted">· <?php echo htmlspecialchars($u['email']); ?></span>
                            <?php if ($u['has_it']): ?><span class="badge bg-secondary ms-1">Already has it</span><?php endif; ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <label class="form-label small fw-bold mt-3" for="giveNote">Gift note (optional)</label>
            <input type="text" class="form-control" id="giveNote" name="note" maxlength="255"
                   placeholder="e.g. Thanks for being a loyal customer!"
                   value="<?php echo htmlspecialchars($_POST['note'] ?? ''); ?>">

            <div class="d-flex justify-content-end gap-2 mt-3">
                <a href="coupons.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> Give voucher</button>
            </div>
        </form>
    </div>
    <script>
    (function () {
        const list = document.getElementById('givePickList');
        document.querySelectorAll('input[name="recipients"]').forEach(r => r.addEventListener('change', () => {
            list.style.display = document.getElementById('giveAll').checked ? 'none' : '';
        }));
        document.getElementById('giveFilter').addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('.give-row').forEach(row => {
                row.style.display = row.dataset.search.includes(q) ? '' : 'none';
            });
        });
    })();
    </script>
    <?php endif; ?>

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
                <div class="col-md-3">
                    <label class="form-label small fw-bold" for="cpDesc">Description</label>
                    <input type="text" class="form-control" id="cpDesc" name="description" maxlength="255"
                           placeholder="e.g. 10% off for new customers"
                           value="<?php echo htmlspecialchars($keep['description'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold" for="cpKind">Voucher for *</label>
                    <select class="form-select" id="cpKind" name="kind" aria-describedby="cpKindHint">
                        <option value="discount">Items (discount)</option>
                        <option value="shipping" <?php echo ($keep['kind'] ?? '') === 'shipping' ? 'selected' : ''; ?>>Shipping fee</option>
                    </select>
                    <div id="cpKindHint" class="form-text">Shipping: 100% = free shipping.</div>
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
                        <th>Given to</th>
                        <th>Used</th>
                        <th>Given</th>
                        <th>Valid</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($coupons)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No coupons yet. Create one above.</td></tr>
                    <?php else: ?>
                        <?php foreach ($coupons as $c): [$label, $color] = couponStatus($c); ?>
                        <tr data-live="coupon-<?php echo (int)$c['id']; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($c['code']); ?></strong>
                                <?php if ($c['description']): ?>
                                <br><span class="text-muted small"><?php echo htmlspecialchars($c['description']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(voucherLabel($c)); ?></td>
                            <td><?php echo (float)$c['min_subtotal'] > 0 ? '₱' . number_format((float)$c['min_subtotal'], 2) : '—'; ?></td>
                            <td><?php echo (int)($heldBy[(int)$c['id']]['held'] ?? 0); ?></td>
                            <td><?php echo (int)$c['times_used']; ?> / <?php echo $c['max_uses'] === null ? '∞' : (int)$c['max_uses']; ?></td>
                            <td>₱<?php echo number_format($givenByCode[$c['code']] ?? 0, 2); ?></td>
                            <td class="small">
                                <?php echo $c['valid_from'] ? date('M d, Y H:i', strtotime($c['valid_from'])) : 'Anytime'; ?>
                                <br>→ <?php echo $c['valid_until'] ? date('M d, Y H:i', strtotime($c['valid_until'])) : 'No expiry'; ?>
                            </td>
                            <td><span class="badge bg-<?php echo $color; ?>"><?php echo $label; ?></span></td>
                            <td class="text-end text-nowrap">
                                <a href="coupons.php?give=<?php echo (int)$c['id']; ?>#givePanel" class="btn btn-sm btn-success">
                                    <i class="fas fa-gift"></i> Give
                                </a>
                                <a href="coupons.php?edit=<?php echo (int)$c['id']; ?>#couponForm" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <form method="POST" action="coupons.php" style="display:inline;" data-ajax><?php echo csrfTokenField(); ?>
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
