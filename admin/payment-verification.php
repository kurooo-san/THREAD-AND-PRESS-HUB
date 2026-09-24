<?php
declare(strict_types=1);

/**
 * Admin — Manual QR Payment verification panel (Part 2).
 *
 * Lists submissions awaiting verification with a proof preview, lets the
 * admin Approve or Reject (with a required reason), blocks approval when the
 * reference number was already used on another order, and supports deleting
 * a proof for privacy. Every action is written to the audit log.
 */

require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/payment-proof-handler.php'; // -> payment-config.php
require_once __DIR__ . '/../includes/paymongo.php';         // gateway payments

// --- Admin gate --------------------------------------------------------
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pageTitle = 'Payment Verification';
$adminId   = (int)$_SESSION['user_id'];
$error     = '';
$success   = '';
$warning   = '';

/** Load a submission with its order + customer, or null. */
function loadSubmission(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare(
        "SELECT ps.*, o.user_id, o.total, o.status AS order_status, o.payment_status,
                u.fullname, u.email
           FROM payment_submissions ps
           JOIN orders o ON o.id = ps.order_id
           JOIN users  u ON u.id = o.user_id
          WHERE ps.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// ---------------------------------------------------------------------
// Handle actions (CSRF-protected).
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } else {
        $action       = (string)($_POST['action'] ?? '');
        $submissionId = (int)($_POST['submission_id'] ?? 0);
        $sub          = $submissionId > 0 ? loadSubmission($conn, $submissionId) : null;

        if (!$sub) {
            $error = 'That payment submission no longer exists.';
        } elseif ($action === 'approve') {
            $orderId   = (int)$sub['order_id'];
            $reference = (string)$sub['reference_number'];
            $dupes     = paymentReferenceUsedElsewhere($conn, $reference, $orderId);
            $override  = isset($_POST['confirm_override']);

            if ($sub['status'] !== 'pending_verification') {
                $error = 'This submission has already been reviewed.';
            } elseif (!empty($dupes) && !$override) {
                // Block auto-approval on a reused receipt; require explicit override.
                $warning = 'Reference "' . htmlspecialchars($reference, ENT_QUOTES)
                    . '" is already used on order(s) #' . implode(', #', $dupes)
                    . '. This may be a reused receipt. Review carefully — to approve anyway, '
                    . 'tick "I have verified this is not a duplicate" and approve again.';
            } else {
                $conn->begin_transaction();
                try {
                    $now = date('Y-m-d H:i:s');
                    $u1 = $conn->prepare(
                        "UPDATE payment_submissions
                            SET status='verified', reviewed_by=?, reviewed_at=?
                          WHERE id=? AND status='pending_verification'"
                    );
                    $u1->bind_param('isi', $adminId, $now, $submissionId);
                    $u1->execute();
                    $changed = $u1->affected_rows;
                    $u1->close();

                    if ($changed !== 1) {
                        throw new \RuntimeException('Submission state changed concurrently.');
                    }

                    // Mark order paid; nudge a still-pending order to confirmed.
                    $u2 = $conn->prepare(
                        "UPDATE orders
                            SET payment_status='verified',
                                status = CASE WHEN status='pending' THEN 'confirmed' ELSE status END
                          WHERE id=?"
                    );
                    $u2->bind_param('i', $orderId);
                    $u2->execute();
                    $u2->close();

                    $conn->commit();
                    logAudit('payment_verified', 'order', $orderId,
                        'Payment submission #' . $submissionId . ' approved'
                        . (!empty($dupes) ? ' (duplicate-ref override)' : ''));
                    $success = 'Payment approved. Order #' . $orderId . ' is now marked Paid.';
                } catch (\Throwable $e) {
                    $conn->rollback();
                    error_log('[payment-verification] approve failed: ' . $e->getMessage());
                    $error = 'Could not approve this payment. Please try again.';
                }
            }
        } elseif ($action === 'reject') {
            $reason  = trim((string)($_POST['reason'] ?? ''));
            $orderId = (int)$sub['order_id'];

            if ($sub['status'] !== 'pending_verification') {
                $error = 'This submission has already been reviewed.';
            } elseif ($reason === '') {
                $error = 'A rejection reason is required so the customer knows what to fix.';
            } elseif (mb_strlen($reason) > 500) {
                $error = 'Please keep the rejection reason under 500 characters.';
            } else {
                $conn->begin_transaction();
                try {
                    $now = date('Y-m-d H:i:s');
                    $u1 = $conn->prepare(
                        "UPDATE payment_submissions
                            SET status='rejected', reject_reason=?, reviewed_by=?, reviewed_at=?
                          WHERE id=? AND status='pending_verification'"
                    );
                    $u1->bind_param('sisi', $reason, $adminId, $now, $submissionId);
                    $u1->execute();
                    $changed = $u1->affected_rows;
                    $u1->close();
                    if ($changed !== 1) {
                        throw new \RuntimeException('Submission state changed concurrently.');
                    }

                    // Order returns to a payable state so the customer can re-submit.
                    $u2 = $conn->prepare("UPDATE orders SET payment_status='rejected' WHERE id=?");
                    $u2->bind_param('i', $orderId);
                    $u2->execute();
                    $u2->close();

                    $conn->commit();
                    logAudit('payment_rejected', 'order', $orderId,
                        'Payment submission #' . $submissionId . ' rejected: ' . $reason);
                    $success = 'Payment rejected. The customer can now re-submit.';
                } catch (\Throwable $e) {
                    $conn->rollback();
                    error_log('[payment-verification] reject failed: ' . $e->getMessage());
                    $error = 'Could not reject this payment. Please try again.';
                }
            }
        } elseif ($action === 'delete_proof') {
            // Privacy action: remove the stored screenshot file but keep the
            // submission record (reference number, status) for the audit trail.
            $abs = paymentResolveProofPath($sub['proof_path'] ?? null);
            if ($abs !== null) {
                @unlink($abs);
            }
            $u = $conn->prepare("UPDATE payment_submissions SET proof_path=NULL WHERE id=?");
            $u->bind_param('i', $submissionId);
            $u->execute();
            $u->close();
            // Also clear it from the order if it referenced the same file.
            $uo = $conn->prepare("UPDATE orders SET payment_proof=NULL WHERE id=? AND payment_proof=?");
            $uo->bind_param('is', $sub['order_id'], $sub['proof_path']);
            $uo->execute();
            $uo->close();
            logAudit('payment_proof_deleted', 'order', (int)$sub['order_id'],
                'Proof for submission #' . $submissionId . ' deleted by admin');
            $success = 'Proof image deleted.';
        } else {
            $error = 'Unknown action.';
        }
    }
}

// ---------------------------------------------------------------------
// Fetch the pending queue (+ a small history of recently reviewed).
// ---------------------------------------------------------------------
$pending = $conn->query(
    "SELECT ps.*, o.total, o.user_id, u.fullname, u.email
       FROM payment_submissions ps
       JOIN orders o ON o.id = ps.order_id
       JOIN users  u ON u.id = o.user_id
      WHERE ps.status = 'pending_verification'
      ORDER BY ps.submitted_at ASC"
);

$recent = $conn->query(
    "SELECT ps.id, ps.order_id, ps.status, ps.reference_number, ps.reviewed_at,
            ps.reject_reason, a.fullname AS reviewer
       FROM payment_submissions ps
       LEFT JOIN users a ON a.id = ps.reviewed_by
      WHERE ps.status <> 'pending_verification'
      ORDER BY ps.reviewed_at DESC, ps.id DESC
      LIMIT 15"
);

/** Pre-compute duplicate references in the pending set for inline warnings. */
function pendingDuplicateIds(mysqli $conn, string $reference, int $orderId): array
{
    return paymentReferenceUsedElsewhere($conn, $reference, $orderId);
}

include __DIR__ . '/../includes/header/header.php';
include __DIR__ . '/../includes/admin-sidebar.php';
?>
<div class="admin-container">
  <div class="mb-4">
    <h1 class="text-coffee-dark mb-2" style="font-size:2rem; font-weight:800;">
      <i class="fas fa-money-check-dollar"></i> Payments
    </h1>
    <p class="text-muted mb-0">
      Online payments are collected and confirmed by PayMongo. The manual proof queue below is kept
      for orders taken before the switch.
    </p>
  </div>

  <?php if ($error): ?>   <div class="alert alert-danger"><?php   echo htmlspecialchars($error, ENT_QUOTES); ?></div><?php endif; ?>
  <?php if ($warning): ?> <div class="alert alert-warning"><?php  echo $warning; /* pre-escaped */ ?></div><?php endif; ?>
  <?php if ($success): ?> <div class="alert alert-success"><?php  echo htmlspecialchars($success, ENT_QUOTES); ?></div><?php endif; ?>

  <?php
  // Gateway payments. Read-only on purpose: PayMongo already confirmed the
  // money, so there is nothing here for an admin to approve or reject — this
  // is for reconciliation and for chasing a payment that got stuck.
  $pmRows = null;
  if (paymongoTableExists()) {
      $pmRows = $conn->query(
          "SELECT s.session_id, s.order_kind, s.order_id, s.amount, s.status,
                  s.payment_id, s.payment_method_used, s.created_at,
                  u.fullname, u.email
             FROM paymongo_sessions s
             LEFT JOIN users u ON u.id = s.user_id
            ORDER BY s.id DESC
            LIMIT 25"
      );
  }
  ?>
  <div class="admin-card mb-4">
    <h2 class="h5 mb-3">
      PayMongo payments
      <?php if (paymongoIsConfigured() && paymongoIsTestMode()): ?>
        <span class="badge bg-warning text-dark" title="A sk_test_ key is configured">Sandbox</span>
      <?php elseif (!paymongoIsConfigured()): ?>
        <span class="badge bg-secondary" title="No PAYMONGO_SECRET_KEY in .env">Not configured</span>
      <?php endif; ?>
    </h2>

    <?php if (!paymongoTableExists()): ?>
      <p class="text-muted mb-0">Run <code>migrate_paymongo.sql</code> to enable the gateway.</p>
    <?php elseif (!$pmRows || $pmRows->num_rows === 0): ?>
      <p class="text-muted mb-0">No online payments yet.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Order</th><th>Customer</th><th>Amount</th>
              <th>Channel</th><th>Status</th><th>Reference</th><th>Started</th>
            </tr>
          </thead>
          <tbody>
          <?php while ($pm = $pmRows->fetch_assoc()):
              $badge = [
                  'paid'    => 'success',
                  'created' => 'secondary',
                  'failed'  => 'danger',
                  'expired' => 'dark',
              ][$pm['status']] ?? 'secondary';
              $isCustom = $pm['order_kind'] === 'custom';
              $link = $isCustom
                  ? '../custom-order-tracking.php?order_id=' . (int)$pm['order_id']
                  : 'order_details.php?id=' . (int)$pm['order_id'];
          ?>
            <tr>
              <td>
                <a href="<?php echo htmlspecialchars($link, ENT_QUOTES); ?>">#<?php echo (int)$pm['order_id']; ?></a>
                <?php if ($isCustom): ?><br><small class="text-muted">custom</small><?php endif; ?>
              </td>
              <td>
                <?php echo htmlspecialchars((string)($pm['fullname'] ?? '—'), ENT_QUOTES); ?>
                <br><small class="text-muted"><?php echo htmlspecialchars((string)($pm['email'] ?? ''), ENT_QUOTES); ?></small>
              </td>
              <td>&#8369;<?php echo number_format((float)$pm['amount'], 2); ?></td>
              <td>
                <?php $ch = (string)($pm['payment_method_used'] ?? ''); ?>
                <?php echo $ch !== '' ? htmlspecialchars(strtoupper($ch), ENT_QUOTES) : '<span class="text-muted">—</span>'; ?>
              </td>
              <td><span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars(ucfirst((string)$pm['status']), ENT_QUOTES); ?></span></td>
              <td>
                <small class="text-muted"><?php
                  // The payment id is what to quote to PayMongo support; the
                  // session id only matters while the payment is unfinished.
                  echo htmlspecialchars((string)($pm['payment_id'] ?: $pm['session_id']), ENT_QUOTES);
                ?></small>
              </td>
              <td><small><?php echo htmlspecialchars(date('M d, H:i', strtotime((string)$pm['created_at'])), ENT_QUOTES); ?></small></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <p class="text-muted small mb-0 mt-2">
        Read-only — PayMongo confirms these automatically. A row stuck on
        <strong>Created</strong> means the customer opened the payment page but never finished.
      </p>
    <?php endif; ?>
  </div>

  <div class="admin-card">
    <h2 class="h5 mb-3">Manual proofs awaiting verification
      <span class="badge bg-warning text-dark"><?php echo $pending ? (int)$pending->num_rows : 0; ?></span>
    </h2>

    <?php if (!$pending || $pending->num_rows === 0): ?>
      <p class="text-muted mb-0">No payments are waiting for verification. 🎉</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Proof</th><th>Order</th><th>Customer</th><th>Amount</th>
              <th>Channel</th><th>Reference</th><th>Submitted</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php while ($row = $pending->fetch_assoc()):
              $sid     = (int)$row['id'];
              $oid     = (int)$row['order_id'];
              $dupes   = pendingDuplicateIds($conn, (string)$row['reference_number'], $oid);
              $proofUrl = !empty($row['proof_path']) ? '../serve-proof.php?id=' . $sid : '';
          ?>
            <tr>
              <td>
                <?php if ($proofUrl !== ''): ?>
                  <a href="<?php echo htmlspecialchars($proofUrl, ENT_QUOTES); ?>" target="_blank" rel="noopener"
                     title="Open full screenshot">
                    <img src="<?php echo htmlspecialchars($proofUrl, ENT_QUOTES); ?>" alt="Payment proof for order #<?php echo $oid; ?>"
                         style="width:64px; height:64px; object-fit:cover; border-radius:8px; border:1px solid #eee;">
                  </a>
                <?php else: ?>
                  <span class="text-muted small">No image</span>
                <?php endif; ?>
              </td>
              <td><strong>#<?php echo $oid; ?></strong></td>
              <td>
                <div><?php echo htmlspecialchars((string)$row['fullname'], ENT_QUOTES); ?></div>
                <small class="text-muted"><?php echo htmlspecialchars((string)$row['email'], ENT_QUOTES); ?></small>
              </td>
              <td><?php echo htmlspecialchars(paymentFormatPeso((float)$row['amount']), ENT_QUOTES); ?>
                <?php if ((float)$row['amount'] !== (float)$row['total']): ?>
                  <br><small class="text-danger">≠ order total <?php echo htmlspecialchars(paymentFormatPeso((float)$row['total']), ENT_QUOTES); ?></small>
                <?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars(ucfirst((string)$row['channel']), ENT_QUOTES); ?></td>
              <td>
                <?php echo htmlspecialchars((string)$row['reference_number'], ENT_QUOTES); ?>
                <?php if (!empty($dupes)): ?>
                  <br><span class="badge bg-danger" title="Same reference on other orders">
                    <i class="fas fa-triangle-exclamation"></i> Dup: #<?php echo implode(', #', $dupes); ?>
                  </span>
                <?php endif; ?>
              </td>
              <td><small><?php echo htmlspecialchars(date('M d, Y H:i', strtotime((string)$row['submitted_at'])), ENT_QUOTES); ?></small></td>
              <td style="min-width:240px;">
                <!-- Approve -->
                <form method="POST" class="mb-2">
                  <?php echo csrfTokenField(); ?>
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="submission_id" value="<?php echo $sid; ?>">
                  <?php if (!empty($dupes)): ?>
                    <div class="form-check form-check-sm mb-1">
                      <input class="form-check-input" type="checkbox" name="confirm_override" id="ov<?php echo $sid; ?>" required>
                      <label class="form-check-label small text-danger" for="ov<?php echo $sid; ?>">
                        I have verified this is not a duplicate
                      </label>
                    </div>
                  <?php endif; ?>
                  <button type="submit" class="btn btn-sm btn-success w-100">
                    <i class="fas fa-check"></i> Approve &amp; mark Paid
                  </button>
                </form>
                <!-- Reject -->
                <form method="POST" onsubmit="return this.reason.value.trim() !== '' || (alert('Please enter a rejection reason.'), false);">
                  <?php echo csrfTokenField(); ?>
                  <input type="hidden" name="action" value="reject">
                  <input type="hidden" name="submission_id" value="<?php echo $sid; ?>">
                  <input type="text" name="reason" class="form-control form-control-sm mb-1"
                         placeholder="Reason (shown to customer)" maxlength="500"
                         aria-label="Rejection reason for order #<?php echo $oid; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                    <i class="fas fa-xmark"></i> Reject
                  </button>
                </form>
                <!-- Delete proof (privacy) -->
                <?php if ($proofUrl !== ''): ?>
                <form method="POST" class="mt-2" onsubmit="return confirm('Permanently delete this proof image? The record is kept for the audit trail.');">
                  <?php echo csrfTokenField(); ?>
                  <input type="hidden" name="action" value="delete_proof">
                  <input type="hidden" name="submission_id" value="<?php echo $sid; ?>">
                  <button type="submit" class="btn btn-sm btn-link text-muted p-0">Delete proof image</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Recently reviewed (lightweight audit view) -->
  <div class="admin-card mt-4">
    <h2 class="h5 mb-3">Recently reviewed</h2>
    <?php if (!$recent || $recent->num_rows === 0): ?>
      <p class="text-muted mb-0">Nothing reviewed yet.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>Order</th><th>Reference</th><th>Result</th><th>By</th><th>When</th><th>Reason</th></tr></thead>
          <tbody>
          <?php while ($r = $recent->fetch_assoc()): ?>
            <tr>
              <td>#<?php echo (int)$r['order_id']; ?></td>
              <td><?php echo htmlspecialchars((string)$r['reference_number'], ENT_QUOTES); ?></td>
              <td>
                <?php if ($r['status'] === 'verified'): ?>
                  <span class="badge bg-success">Verified</span>
                <?php else: ?>
                  <span class="badge bg-danger">Rejected</span>
                <?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars((string)($r['reviewer'] ?? '—'), ENT_QUOTES); ?></td>
              <td><small><?php echo $r['reviewed_at'] ? htmlspecialchars(date('M d, H:i', strtotime((string)$r['reviewed_at'])), ENT_QUOTES) : '—'; ?></small></td>
              <td><small class="text-muted"><?php echo htmlspecialchars((string)($r['reject_reason'] ?? ''), ENT_QUOTES); ?></small></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->
<script src="../js/admin-sidebar.js"></script>
<?php include __DIR__ . '/../includes/footer/footer.php'; ?>
