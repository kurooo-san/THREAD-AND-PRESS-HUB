<?php
declare(strict_types=1);

/**
 * Admin — Custom Design Prices: base price per apparel type, print sizes,
 * sleeve/logo prints and extra colors.
 *
 * The Design Studio, My Designs, the order summary and the chatbot all read
 * includes/apparel-config.php, so a change here applies everywhere at once.
 * Orders already placed keep the price saved on them. The apparel types and
 * print sizes themselves are fixed.
 */

require __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/apparel-config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$apparel = apparelConfigDefaults();
$groups  = [
    'base' => [
        'title'  => 'Apparel base price',
        'labels' => array_map(fn($t) => $t['label'] . ($t['couple'] ? ' (per set)' : ''), $apparel),
    ],
    'print' => ['title' => 'Print size', 'labels' => getPrintSizeLabels()],
    'extra' => [
        'title'  => 'Extras',
        'labels' => ['sleeve' => 'Each printed sleeve', 'logo' => 'Logo print', 'color' => 'Each extra color (the first is free)'],
    ],
];

$pageTitle = 'Custom Design Prices';
$error     = '';
$success   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'reset') {
        if (customPricesReset()) {
            logAudit('custom_prices_reset', 'config', null, 'Custom design prices reset to the defaults');
            $success = 'Custom design prices reset to the original amounts.';
        } else {
            $error = 'Could not reset. Check that the storage folder is writable.';
        }
    } else {
        $current = customPrices();
        $new     = $current;
        $changes = [];
        foreach ($groups as $group => $info) {
            foreach ($info['labels'] as $key => $label) {
                $posted = $_POST['price'][$group][$key] ?? '';
                $why    = customPriceError($posted, $group);
                if ($why !== null) {
                    $error = $label . ': ' . $why;
                    break 2;
                }
                $new[$group][$key] = (int) $posted;
                if ($new[$group][$key] !== $current[$group][$key]) {
                    $changes[] = $label . ' ₱' . number_format($current[$group][$key]) . ' to ₱' . number_format($new[$group][$key]);
                }
            }
        }
        if ($error === '') {
            if (!$changes) {
                $success = 'No changes to save.';
            } elseif (customPricesSave($new)) {
                logAudit('custom_prices_updated', 'config', null, 'Custom design prices changed: ' . implode('; ', $changes));
                $success = 'Prices saved. New custom orders use them right away.';
            } else {
                $error = 'Could not save. Check that the storage folder is writable.';
            }
        }
    }
}

$prices   = customPrices();
$defaults = customPriceDefaults();

include __DIR__ . '/../includes/header/header.php';
include __DIR__ . '/../includes/admin-sidebar.php';
// Bad input: show what the admin typed so they can fix it instead of retyping.
$val = static function (string $group, string $key, int $saved) use ($error): string {
    $v = $error !== '' ? ($_POST['price'][$group][$key] ?? $saved) : $saved;
    return htmlspecialchars(is_string($v) || is_int($v) ? (string) $v : '', ENT_QUOTES);
};
?>
<div class="admin-container">
  <div class="mb-4">
    <h1 class="text-coffee-dark mb-2" style="font-size:2rem; font-weight:800;">
      <i class="fas fa-tags"></i> Custom Design Prices
    </h1>
    <p class="text-muted mb-0">What customers pay for a custom design. The Design Studio, order summary and chatbot all use these.
      Orders already placed keep the price saved on them.</p>
  </div>

  <?php if ($error): ?>  <div class="alert alert-danger"><?php  echo htmlspecialchars($error, ENT_QUOTES); ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div><?php endif; ?>

  <form method="POST">
    <?php echo csrfTokenField(); ?>
    <?php foreach ($groups as $group => $info): $min = $group === 'base' ? CUSTOM_BASE_PRICE_MIN : 0; ?>
    <div class="admin-card mb-3">
      <h2 class="h5 mb-3"><?php echo htmlspecialchars($info['title'], ENT_QUOTES); ?></h2>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Item</th>
              <th style="width:220px;">Price (₱)</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($info['labels'] as $key => $label): $safeLabel = htmlspecialchars($label, ENT_QUOTES); ?>
            <tr>
              <td>
                <strong><?php echo $safeLabel; ?></strong>
                <div><small class="text-muted">Default: ₱<?php echo number_format($defaults[$group][$key]); ?></small></div>
              </td>
              <td>
                <input type="number" class="form-control" name="price[<?php echo $group; ?>][<?php echo $key; ?>]" required
                       min="<?php echo $min; ?>" max="<?php echo CUSTOM_PRICE_MAX; ?>" step="1"
                       value="<?php echo $val($group, $key, $prices[$group][$key]); ?>"
                       aria-label="Price of <?php echo $safeLabel; ?>">
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>
    <p class="small text-muted mb-0">
      <i class="fas fa-circle-info"></i> Whole pesos only. PWD and Senior Citizen customers still get 20% off the total.
    </p>
    <div class="d-flex gap-2 mt-4 flex-wrap">
      <button type="submit" class="btn btn-dark"><i class="fas fa-save"></i> Save prices</button>
    </div>
  </form>

  <form method="POST" class="mt-3"
        onsubmit="return confirm('Reset every custom design price to the original amount?');">
    <?php echo csrfTokenField(); ?>
    <input type="hidden" name="action" value="reset">
    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fas fa-rotate-left"></i> Reset to defaults</button>
  </form>
</div>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->
<script src="../js/admin-sidebar.js"></script>
<?php include __DIR__ . '/../includes/footer/footer.php'; ?>
