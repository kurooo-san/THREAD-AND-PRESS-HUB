<?php
declare(strict_types=1);

/**
 * Manual QR Payment — customer checkout / proof-of-payment page (Part 1).
 *
 * Shows the exact amount due, the merchant QR for the chosen channel, clear
 * pay-and-upload instructions, and collects a screenshot + reference number.
 * On submit it records a payment submission and moves the order to
 * "Awaiting Verification".
 */

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/payment-proof-handler.php'; // pulls in payment-config.php
require_once __DIR__ . '/includes/payment-success.php';
redirectToLogin();

$pageTitle = 'Pay for your order';
$userId    = (int)$_SESSION['user_id'];
$orderId   = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

$error   = '';
$success = '';

// ---------------------------------------------------------------------
// Load the order and assert ownership.
// ---------------------------------------------------------------------
$order = null;
if ($orderId > 0) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param('ii', $orderId, $userId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$order) {
    // Render a friendly, self-contained error (do not leak whether the
    // order exists for another user).
    include __DIR__ . '/includes/header/header.php';
    echo '<div class="container my-5"><div class="alert alert-danger" role="alert">'
       . 'We could not find that order under your account. '
       . '<a href="orders.php">Return to your orders</a>.</div></div>';
    include __DIR__ . '/includes/footer/footer.php';
    exit;
}

$amountDue      = (float)$order['total'];
$paymentStatus  = (string)$order['payment_status'];
$orderStatus    = (string)$order['status'];
$enabledChannels = paymentEnabledChannels();

// A payment can be (re)submitted only while the order is in a payable state.
$isPayable = in_array($paymentStatus, ['unpaid', 'rejected'], true)
          && !in_array($orderStatus, ['cancelled', 'completed'], true);

// Surface the most recent rejection reason, if any, so the customer knows
// why a previous attempt failed.
$rejectReason = '';
if ($paymentStatus === 'rejected') {
    $rstmt = $conn->prepare(
        "SELECT reject_reason FROM payment_submissions
          WHERE order_id = ? AND status = 'rejected'
          ORDER BY reviewed_at DESC, id DESC LIMIT 1"
    );
    $rstmt->bind_param('i', $orderId);
    $rstmt->execute();
    if ($rr = $rstmt->get_result()->fetch_assoc()) {
        $rejectReason = (string)($rr['reject_reason'] ?? '');
    }
    $rstmt->close();
}

// ---------------------------------------------------------------------
// Handle submission.
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } elseif (!$isPayable) {
        $error = 'This order is not awaiting payment.';
    } elseif (empty($enabledChannels)) {
        $error = 'Online payment is temporarily unavailable. Please contact support.';
    } else {
        $channel   = (string)($_POST['channel'] ?? '');
        $reference = trim((string)($_POST['reference_number'] ?? ''));
        $consent   = isset($_POST['consent']);

        if (!array_key_exists($channel, $enabledChannels)) {
            $error = 'Please select a valid payment channel.';
        } elseif (!$consent) {
            $error = 'Please tick the consent box so we may use your screenshot to verify this payment.';
        } elseif ($reference === '') {
            $error = 'Please enter the reference number from your payment receipt.';
        } elseif (mb_strlen($reference) > 100) {
            $error = 'That reference number looks too long. Please check and re-enter it.';
        } else {
            // Store the proof securely (real MIME check + EXIF strip inside).
            $stored = storePaymentProof($_FILES['payment_proof'] ?? [], $orderId);
            if (!$stored['ok']) {
                $error = $stored['error'] ?? 'We could not process your screenshot.';
            } else {
                // Transaction: insert submission + flip order to awaiting.
                $conn->begin_transaction();
                try {
                    $ins = $conn->prepare(
                        "INSERT INTO payment_submissions
                            (order_id, channel, amount, reference_number, proof_path, status)
                         VALUES (?, ?, ?, ?, ?, 'pending_verification')"
                    );
                    $ins->bind_param('isdss', $orderId, $channel, $amountDue, $reference, $stored['path']);
                    $ins->execute();
                    $ins->close();

                    $upd = $conn->prepare(
                        "UPDATE orders
                            SET payment_status = 'pending_verification',
                                payment_reference = ?,
                                payment_proof = ?
                          WHERE id = ? AND user_id = ?"
                    );
                    $upd->bind_param('ssii', $reference, $stored['path'], $orderId, $userId);
                    $upd->execute();
                    $upd->close();

                    $conn->commit();

                    // Order placed & paid-pending: the cart is no longer needed
                    // (mirrors the previous per-channel payment pages).
                    $_SESSION['cart'] = [];

                    logAudit('payment_submitted', 'order', $orderId,
                        'Manual QR payment proof submitted via ' . $channel);

                    $paymentStatus = 'pending_verification';
                    $isPayable = false;
                    $success = 'We received your payment proof. Your order is awaiting verification. '
                             . 'We will confirm it shortly — you can check the status anytime on your Orders page.';
                } catch (\Throwable $e) {
                    $conn->rollback();
                    // Clean up the orphaned proof file we just wrote.
                    $abs = paymentResolveProofPath($stored['path']);
                    if ($abs !== null) {
                        @unlink($abs);
                    }
                    error_log('[payment-qr] submission failed for order ' . $orderId . ': ' . $e->getMessage());
                    $error = 'Something went wrong while saving your payment. Please try again.';
                }
            }
        }
    }
}

include __DIR__ . '/includes/header/header.php';
?>
<div class="container my-5">
  <div class="row justify-content-center">
    <div class="col-lg-7 col-md-9">

      <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item"><a href="orders.php">Orders</a></li>
          <li class="breadcrumb-item active" aria-current="page">Payment</li>
        </ol>
      </nav>

      <?php if ($success !== ''): ?>
        <?php
        // Only reachable straight after a successful POST, so it plays once.
        renderPaymentSuccess([
            'title'   => 'Payment Submitted!',
            'message' => 'We received your proof of payment. We will verify it shortly and confirm your order.',
            'badge'   => 'Order #' . (int) $orderId,
            'amount'  => $order['total'] ?? null,
        ]);
        ?>
        <div class="card" style="border:1px solid var(--border-light, #e5e5e5); border-radius:12px;">
          <div class="card-body p-4 text-center">
            <i class="fas fa-circle-check" style="font-size:3rem; color:#27ae60;" aria-hidden="true"></i>
            <h1 class="h4 mt-3">Payment proof received</h1>
            <p class="text-muted mb-4"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></p>
            <a href="orders.php" class="btn btn-dark">Go to my orders</a>
          </div>
        </div>

      <?php elseif ($paymentStatus === 'verified'): ?>
        <div class="alert alert-success" role="alert">
          <strong>This order is already paid.</strong> Thank you! There is nothing more to do here.
        </div>
        <a href="orders.php" class="btn btn-outline-dark">Back to orders</a>

      <?php elseif ($paymentStatus === 'pending_verification'): ?>
        <div class="alert alert-info" role="alert">
          <strong>Your payment is awaiting verification.</strong>
          We are reviewing your screenshot and will confirm your order shortly.
        </div>
        <a href="orders.php" class="btn btn-outline-dark">Back to orders</a>

      <?php elseif (!$isPayable): ?>
        <div class="alert alert-warning" role="alert">
          This order is not currently awaiting payment.
        </div>
        <a href="orders.php" class="btn btn-outline-dark">Back to orders</a>

      <?php elseif (empty($enabledChannels)): ?>
        <div class="alert alert-warning" role="alert">
          Online payment is temporarily unavailable. Please contact support to complete your order.
        </div>

      <?php else: ?>
        <h1 class="h3 mb-1">Complete your payment</h1>
        <p class="text-muted mb-4">Order <strong>#<?php echo (int)$order['id']; ?></strong></p>

        <?php if ($rejectReason !== ''): ?>
          <div class="alert alert-danger" role="alert">
            <strong>Your previous payment was rejected:</strong>
            <?php echo htmlspecialchars($rejectReason, ENT_QUOTES); ?><br>
            Please pay again and upload a fresh, clear screenshot below.
          </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
          <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
        <?php endif; ?>

        <!-- Amount due -->
        <div class="card mb-4" style="border:1px solid var(--border-light, #e5e5e5); border-radius:12px;">
          <div class="card-body text-center p-4">
            <div class="text-muted text-uppercase" style="letter-spacing:.05em; font-size:.8rem;">Amount due</div>
            <div style="font-size:2.25rem; font-weight:800; line-height:1.1;">
              Pay exactly <?php echo htmlspecialchars(paymentFormatPeso($amountDue), ENT_QUOTES); ?>
            </div>
            <div class="text-muted mt-1" style="font-size:.9rem;">
              Enter this exact amount when you scan — it speeds up verification.
            </div>
          </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="qrPayForm" novalidate>
          <?php echo csrfTokenField(); ?>

          <?php
            // Single source of truth: only channels enabled in admin Payment
            // Settings appear here. With one channel we skip the picker.
            $multiChannel = count($enabledChannels) > 1;
            $firstKey     = (string)array_key_first($enabledChannels);
          ?>

          <?php if ($multiChannel): ?>
          <!-- Channel picker (only shown when more than one channel is enabled) -->
          <fieldset class="mb-4">
            <legend class="h6">1. Choose how you want to pay</legend>
            <div class="row g-2" role="radiogroup">
              <?php $first = true; foreach ($enabledChannels as $key => $ch): ?>
                <div class="col-auto">
                  <label class="channel-option">
                    <input type="radio" name="channel" value="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>"
                           <?php echo $first ? 'checked' : ''; ?> required>
                    <span><?php echo htmlspecialchars((string)$ch['display_name'], ENT_QUOTES); ?></span>
                  </label>
                </div>
              <?php $first = false; endforeach; ?>
            </div>
          </fieldset>
          <?php else: ?>
            <!-- One channel enabled: submit it implicitly. -->
            <input type="hidden" name="channel" value="<?php echo htmlspecialchars($firstKey, ENT_QUOTES); ?>">
          <?php endif; ?>

          <!-- Per-channel QR + account details + how-to-pay steps -->
          <fieldset class="mb-4">
            <legend class="h6"><?php echo $multiChannel ? '2.' : '1.'; ?> Pay via QR</legend>
            <?php $first = true; foreach ($enabledChannels as $key => $ch):
                $img    = trim((string)($ch['qr_image'] ?? ''));
                $imgUrl = $img !== '' ? PAYMENT_QR_IMAGE_URLBASE . rawurlencode($img) : '';
                // Treat blank or the "—" placeholder as "no account number".
                $acctNo  = trim((string)($ch['account_no'] ?? ''));
                $hasAcct = $acctNo !== '' && $acctNo !== '—';
            ?>
              <div class="channel-panel" data-channel="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>"
                   <?php echo $first ? '' : 'hidden'; ?>>
                <div class="card" style="border:1px solid var(--border-light, #e5e5e5); border-radius:12px;">
                  <div class="card-body p-4 text-center">
                    <div class="fw-bold mb-2"><?php echo htmlspecialchars((string)$ch['display_name'], ENT_QUOTES); ?></div>
                    <?php if ($imgUrl !== ''): ?>
                      <img src="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES); ?>"
                           alt="<?php echo htmlspecialchars((string)$ch['display_name'] . ' payment QR code', ENT_QUOTES); ?>"
                           style="max-width:260px; width:100%; height:auto; border:1px solid #eee; border-radius:8px;">
                    <?php else: ?>
                      <div class="alert alert-secondary mb-3">QR image not set for this channel — use the account details below.</div>
                    <?php endif; ?>
                    <div class="mt-3">
                      <?php if ((string)$ch['account_name'] !== ''): ?>
                        <div><strong>Account name:</strong> <?php echo htmlspecialchars((string)$ch['account_name'], ENT_QUOTES); ?></div>
                      <?php endif; ?>
                      <?php if ($hasAcct): ?>
                        <div class="text-muted">…or send to <strong><?php echo htmlspecialchars($acctNo, ENT_QUOTES); ?></strong></div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php $first = false; endforeach; ?>

            <ol class="mt-3 mb-0" style="font-size:.95rem;">
              <li>Open your e-wallet or banking app.</li>
              <li>Scan the QR code above.</li>
              <li>Enter the <strong>exact</strong> amount: <?php echo htmlspecialchars(paymentFormatPeso($amountDue), ENT_QUOTES); ?>
                  <small class="text-muted">(this static QR doesn't pre-fill the amount).</small></li>
              <li>Complete the payment and take a screenshot of the receipt.</li>
              <li>Upload the screenshot and enter the reference number below.</li>
            </ol>
          </fieldset>

          <!-- Proof + reference -->
          <fieldset class="mb-4">
            <legend class="h6"><?php echo $multiChannel ? '3.' : '2.'; ?> Upload your receipt screenshot &amp; reference number</legend>

            <div class="mb-3">
              <label class="form-label" for="proofInput">Payment screenshot <span class="text-danger">*</span></label>
              <input class="form-control" type="file" id="proofInput" name="payment_proof"
                     accept="image/png,image/jpeg,image/webp" required
                     aria-describedby="proofHelp">
              <small id="proofHelp" class="text-muted">PNG, JPG or WEBP, up to 5&nbsp;MB.</small>
              <img id="proofPreview" alt="Preview of your selected screenshot"
                   style="display:none; max-width:200px; margin-top:.75rem; border-radius:8px; border:1px solid #eee;">
            </div>

            <div class="mb-3">
              <label class="form-label" for="refInput">Reference number <span class="text-danger">*</span></label>
              <input class="form-control" type="text" id="refInput" name="reference_number"
                     maxlength="100" required value="<?php echo htmlspecialchars($_POST['reference_number'] ?? '', ENT_QUOTES); ?>"
                     aria-describedby="refHelp">
              <small id="refHelp" class="text-muted">
                Find this on your payment receipt / transaction details (often labelled
                "Ref No." or "Reference ID"). It lets us match your payment quickly.
              </small>
            </div>
          </fieldset>

          <!-- Consent + privacy (RA 10173) -->
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="consent" name="consent" required>
            <label class="form-check-label" for="consent">
              I understand my screenshot may contain personal information and consent to it being
              used <strong>only to verify this payment</strong>.
            </label>
          </div>

          <details class="mb-4">
            <summary style="cursor:pointer;">Privacy notice (Data Privacy Act, RA 10173)</summary>
            <div class="text-muted mt-2" style="font-size:.88rem;">
              <p class="mb-1"><strong>What we collect:</strong> your payment screenshot and reference number.</p>
              <p class="mb-1"><strong>Why:</strong> solely to confirm that this specific order has been paid.</p>
              <p class="mb-1"><strong>Who sees it:</strong> only our authorised staff, through a secure
                 login-protected viewer. The file is never publicly accessible.</p>
              <p class="mb-0"><strong>Retention:</strong> proofs are automatically deleted
                 <?php echo (int)PAYMENT_PROOF_RETENTION_DAYS; ?> days after the order is completed or
                 cancelled. You may request earlier deletion by contacting support.</p>
            </div>
          </details>

          <button type="submit" class="btn btn-dark w-100" style="padding:.75rem;">
            Submit payment proof
          </button>
          <a href="orders.php" class="btn btn-outline-dark w-100 mt-2" style="padding:.75rem;">Cancel</a>
        </form>
      <?php endif; ?>

    </div>
  </div>
</div>

<style>
  .channel-option { display:inline-flex; align-items:center; gap:.4rem; border:1px solid #ddd;
    border-radius:999px; padding:.45rem .9rem; cursor:pointer; user-select:none; }
  .channel-option input { accent-color:#1a1a1a; }
  .channel-option:focus-within { outline:2px solid #1a1a1a; outline-offset:2px; }
</style>

<script>
/* Show only the QR panel for the selected channel + live screenshot preview. */
(function () {
  const form = document.getElementById('qrPayForm');
  if (!form) return;

  const panels = form.querySelectorAll('.channel-panel');
  form.querySelectorAll('input[name="channel"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      panels.forEach(function (p) {
        p.hidden = (p.getAttribute('data-channel') !== radio.value);
      });
    });
  });

  const input = document.getElementById('proofInput');
  const preview = document.getElementById('proofPreview');
  if (input && preview) {
    input.addEventListener('change', function () {
      const file = input.files && input.files[0];
      if (!file) { preview.style.display = 'none'; return; }
      const reader = new FileReader();
      reader.onload = function (e) { preview.src = e.target.result; preview.style.display = 'block'; };
      reader.readAsDataURL(file);
    });
  }
})();
</script>

<?php include __DIR__ . '/includes/footer/footer.php'; ?>
