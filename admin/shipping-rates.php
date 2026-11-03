<?php
declare(strict_types=1);

/**
 * Admin — Shipping Rates: the delivery fee and delivery days for each area.
 *
 * Checkout, the order summary, the Shipping page, the product ETA and both
 * chatbots all read DELIVERY_ZONES (includes/delivery-zones.php), so a change
 * here applies everywhere at once. Orders already placed keep the fee they
 * were charged. The areas themselves are fixed: checkout matches addresses
 * to them by province.
 */

require __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/delivery-zones.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pageTitle = 'Shipping Rates';
$error     = '';
$success   = '';
$zones     = DELIVERY_ZONES;   // what the form shows; replaced by the posted values on save

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'reset') {
        if (!is_file(DELIVERY_RATES_FILE) || @unlink(DELIVERY_RATES_FILE)) {
            $zones = DELIVERY_ZONE_DEFAULTS;
            logAudit('shipping_rates_reset', 'config', null, 'Shipping rates reset to the defaults');
            $success = 'Shipping rates reset to the original fees and delivery days.';
        } else {
            $error = 'Could not reset. Check that the storage folder is writable.';
        }
    } else {
        $rates   = [];
        $changes = [];
        foreach (DELIVERY_ZONES as $key => $zone) {
            $fee  = trim((string) ($_POST['fee'][$key] ?? ''));
            $from = trim((string) ($_POST['days_from'][$key] ?? ''));
            $to   = trim((string) ($_POST['days_to'][$key] ?? ''));
            $why  = deliveryRateError($fee, $from, $to);
            if ($why !== null) {
                $error = $zone['label'] . ': ' . $why;
                break;
            }
            $rates[$key] = ['fee' => round((float) $fee, 2), 'days' => [(int) $from, (int) $to]];
            if ($rates[$key]['fee'] != $zone['fee'] || $rates[$key]['days'] !== $zone['days']) {
                $changes[] = $zone['label'] . ' ₱' . number_format($rates[$key]['fee'], 2)
                    . ' (' . $from . '-' . $to . ' days)';
            }
        }
        if ($error === '') {
            if (!$changes) {
                $success = 'No changes to save.';
            } elseif (deliveryRatesSave($rates)) {
                foreach ($rates as $key => $r) {
                    $zones[$key]['fee']  = $r['fee'];
                    $zones[$key]['days'] = $r['days'];
                }
                logAudit('shipping_rates_updated', 'config', null, 'Shipping rates changed: ' . implode('; ', $changes));
                $success = 'Shipping rates saved. New orders use them right away.';
            } else {
                $error = 'Could not save. Check that the storage folder is writable.';
            }
        }
    }
}

include __DIR__ . '/../includes/header/header.php';
include __DIR__ . '/../includes/admin-sidebar.php';
// Bad input: show what the admin typed so they can fix it instead of retyping.
$val = static function (string $field, string $key, $saved) use ($error): string {
    $v = $error !== '' ? ($_POST[$field][$key] ?? $saved) : $saved;
    return htmlspecialchars(is_string($v) || is_numeric($v) ? (string) $v : '', ENT_QUOTES);
};
?>
<div class="admin-container">
  <div class="mb-4">
    <h1 class="text-coffee-dark mb-2" style="font-size:2rem; font-weight:800;">
      <i class="fas fa-truck"></i> Shipping Rates
    </h1>
    <p class="text-muted mb-0">The delivery fee and delivery time for each area. Checkout, the Shipping page, product pages and the chatbot all use these.
      Orders already placed keep the fee they were charged. Store Pickup is always free.</p>
  </div>

  <?php if ($error): ?>  <div class="alert alert-danger"><?php  echo htmlspecialchars($error, ENT_QUOTES); ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div><?php endif; ?>

  <form method="POST">
    <?php echo csrfTokenField(); ?>
    <div class="admin-card">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Delivery area</th>
              <th style="min-width:150px;">Fee (₱)</th>
              <th style="min-width:210px;">Delivery time (business days)</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($zones as $key => $zone): $def = DELIVERY_ZONE_DEFAULTS[$key]; ?>
            <tr>
              <td>
                <strong><?php echo htmlspecialchars($zone['label'], ENT_QUOTES); ?></strong>
                <div><small class="text-muted">Default: ₱<?php echo number_format($def['fee'], 2); ?>, <?php echo (int) $def['days'][0] . '-' . (int) $def['days'][1]; ?> days</small></div>
              </td>
              <td>
                <input type="number" class="form-control" name="fee[<?php echo $key; ?>]" required
                       min="0" max="<?php echo (int) DELIVERY_FEE_MAX; ?>" step="0.01"
                       value="<?php echo $val('fee', $key, number_format((float) $zone['fee'], 2, '.', '')); ?>"
                       aria-label="Fee for <?php echo htmlspecialchars($zone['label'], ENT_QUOTES); ?>">
              </td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <input type="number" class="form-control" name="days_from[<?php echo $key; ?>]" required
                         min="1" max="<?php echo DELIVERY_DAYS_MAX; ?>" step="1" style="max-width:80px;"
                         value="<?php echo $val('days_from', $key, (int) $zone['days'][0]); ?>"
                         aria-label="Fastest delivery in days for <?php echo htmlspecialchars($zone['label'], ENT_QUOTES); ?>">
                  <span>to</span>
                  <input type="number" class="form-control" name="days_to[<?php echo $key; ?>]" required
                         min="1" max="<?php echo DELIVERY_DAYS_MAX; ?>" step="1" style="max-width:80px;"
                         value="<?php echo $val('days_to', $key, (int) $zone['days'][1]); ?>"
                         aria-label="Slowest delivery in days for <?php echo htmlspecialchars($zone['label'], ENT_QUOTES); ?>">
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="small text-muted mt-3 mb-0">
        <i class="fas fa-circle-info"></i> Tip: compare with your couriers' current rates so the fee covers the parcel cost.
        Business days skip Sundays.
      </p>
    </div>
    <div class="d-flex gap-2 mt-4 flex-wrap">
      <button type="submit" class="btn btn-dark"><i class="fas fa-save"></i> Save shipping rates</button>
    </div>
  </form>

  <form method="POST" class="mt-3"
        onsubmit="return confirm('Reset every area to the original fee and delivery days?');">
    <?php echo csrfTokenField(); ?>
    <input type="hidden" name="action" value="reset">
    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fas fa-rotate-left"></i> Reset to defaults</button>
  </form>
</div>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->
<script src="../js/admin-sidebar.js"></script>
<?php include __DIR__ . '/../includes/footer/footer.php'; ?>
