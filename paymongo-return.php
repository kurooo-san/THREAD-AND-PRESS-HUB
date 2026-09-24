<?php

/**
 * PayMongo — where the customer lands after the hosted checkout page.
 *
 * SECURITY NOTE: arriving here proves nothing. Anyone can type this URL. The
 * order is only marked paid after paymongoFulfilSession() re-reads the
 * session straight from the PayMongo API, so a forged "?result=success" does
 * nothing at all.
 */

require 'includes/config.php';
require_once 'includes/paymongo.php';
require_once 'includes/paymongo-fulfil.php';
redirectToLogin();

$pageTitle = 'Payment result';

$userId  = (int) $_SESSION['user_id'];
$kind    = ($_GET['kind'] ?? 'order') === 'custom' ? 'custom' : 'order';
$orderId = (int) ($_GET['order_id'] ?? 0);
$result  = (string) ($_GET['result'] ?? '');

$trackUrl = $kind === 'custom'
    ? 'custom-order-tracking.php?order_id=' . $orderId
    : 'order_confirmation.php?order_id=' . $orderId;
$retryUrl = 'paymongo-checkout.php?kind=' . $kind . '&order_id=' . $orderId;

if ($orderId <= 0) {
    $_SESSION['error'] = 'No order was specified.';
    header('Location: orders.php');
    exit;
}

// The session we opened for this order. PayMongo does not append its own id
// to the return URL, so we look it up from our own bookkeeping.
$session = null;
if (paymongoTableExists()) {
    $stmt = $conn->prepare(
        "SELECT * FROM paymongo_sessions
          WHERE order_kind = ? AND order_id = ? AND user_id = ?
          ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param('sii', $kind, $orderId, $userId);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

if ($session === null) {
    $_SESSION['error'] = 'We could not find a payment for that order.';
    header('Location: ' . $trackUrl);
    exit;
}

// The customer pressed "cancel" on PayMongo's page. Nothing was charged, so
// leave the order alone and let them try again.
if ($result === 'cancel') {
    if (($session['status'] ?? '') !== 'paid') {
        paymongoUpdateSessionStatus((string) $session['session_id'], 'failed');
    }
    $_SESSION['error'] = 'Payment was cancelled. Your order is still waiting — you can pay again anytime.';
    header('Location: ' . $trackUrl);
    exit;
}

$outcome = paymongoFulfilSession((string) $session['session_id']);

if ($outcome['ok'] && $outcome['paid']) {
    require_once 'includes/payment-success.php';
    setPaymentSuccessFlash([
        'order_id' => $orderId,
        'kind'     => $kind === 'custom' ? 'custom_paymongo' : 'order_paymongo',
        'total'    => (float) $session['amount'],
    ]);

    // Non-fatal: a receipt that fails to send must not lose a real payment.
    try {
        require_once 'includes/email-helper.php';
        if ($kind === 'order' && function_exists('sendOrderConfirmationEmail')) {
            sendOrderConfirmationEmail($conn, $orderId);
        }
    } catch (Throwable $e) {
        error_log('[paymongo] receipt email failed: ' . $e->getMessage());
    }

    header('Location: ' . $trackUrl);
    exit;
}

// Paid-but-not-yet-visible is a normal race: some channels confirm a moment
// after the redirect. Show a holding page that re-checks instead of telling
// the customer their money vanished.
$pending = $outcome['ok'] && !$outcome['paid'];
$message = $outcome['error'] !== '' ? $outcome['error'] : '';

include 'includes/header/header.php';
?>
<div class="container my-5">
  <div class="row justify-content-center">
    <div class="col-md-7">
      <div class="card" style="border:1px solid var(--border-light); border-radius: var(--radius-md);">
        <div class="card-body p-4 text-center">

          <?php if ($pending): ?>
            <div style="font-size:2.5rem; line-height:1;" aria-hidden="true">⏳</div>
            <h1 class="h4 mt-3 mb-2" style="font-weight:700;">Still confirming your payment</h1>
            <p class="text-muted">
              PayMongo has not confirmed this payment yet. This page checks again automatically —
              if you completed the payment, it will update in a moment.
            </p>
            <p class="text-muted small">Order #<?php echo (int) $orderId; ?></p>
            <div class="mt-4 d-flex gap-2 justify-content-center flex-wrap">
              <a class="btn btn-dark" href="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES); ?>">Check again</a>
              <a class="btn btn-outline-dark" href="<?php echo htmlspecialchars($trackUrl, ENT_QUOTES); ?>">View my order</a>
            </div>
            <?php // One automatic retry after 6s covers the usual confirmation lag. ?>
            <script>
              if (!sessionStorage.getItem('tphPmRetry<?php echo (int) $orderId; ?>')) {
                sessionStorage.setItem('tphPmRetry<?php echo (int) $orderId; ?>', '1');
                setTimeout(function () { window.location.reload(); }, 6000);
              }
            </script>
          <?php else: ?>
            <div style="font-size:2.5rem; line-height:1;" aria-hidden="true">⚠️</div>
            <h1 class="h4 mt-3 mb-2" style="font-weight:700;">We could not confirm that payment</h1>
            <p class="text-muted"><?php echo htmlspecialchars($message ?: 'Something went wrong while confirming your payment.', ENT_QUOTES); ?></p>
            <p class="text-muted small">
              Nothing has been charged to your order. If money did leave your account,
              contact us with order #<?php echo (int) $orderId; ?> and we will sort it out.
            </p>
            <div class="mt-4 d-flex gap-2 justify-content-center flex-wrap">
              <a class="btn btn-dark" href="<?php echo htmlspecialchars($retryUrl, ENT_QUOTES); ?>">Try paying again</a>
              <a class="btn btn-outline-dark" href="<?php echo htmlspecialchars($trackUrl, ENT_QUOTES); ?>">View my order</a>
            </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</div>
<?php include 'includes/footer/footer.php'; ?>
