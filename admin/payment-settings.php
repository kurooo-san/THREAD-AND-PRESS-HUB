<?php
declare(strict_types=1);

/**
 * Admin — Manual QR Payment channel settings.
 *
 * Lets an admin enable/disable each channel (InstaPay / GCash / Maya), set
 * the display name + account details, and upload the merchant "receive" QR
 * image shown to customers at checkout.
 *
 * QR images are the merchant's own receive codes (not sensitive personal
 * data), so they live in the public images/payment-qr/ folder. They are
 * still validated by real MIME type and size on upload.
 */

require __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/payment-config.php';

// --- Admin gate --------------------------------------------------------
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pageTitle = 'Payment Settings';
$error     = '';
$success   = '';
$channels  = paymentGetChannels();

/** Max size for an uploaded QR image. */
const QR_IMAGE_MAX_BYTES = 3 * 1024 * 1024; // 3 MB

/**
 * Validate + store an uploaded QR image into the public QR folder.
 *
 * @return array{ok:bool, filename:?string, error:?string}
 */
function storeQrImage(array $file, string $channelKey): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'filename' => null, 'error' => null]; // no new file = keep existing
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'filename' => null, 'error' => 'QR image upload failed.'];
    }
    if (($file['size'] ?? 0) <= 0 || $file['size'] > QR_IMAGE_MAX_BYTES) {
        return ['ok' => false, 'filename' => null, 'error' => 'QR image must be under 3 MB.'];
    }
    $tmp = $file['tmp_name'] ?? '';
    if (!is_string($tmp) || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'filename' => null, 'error' => 'Invalid QR upload.'];
    }
    $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!isset(PAYMENT_PROOF_ALLOWED[$mime]) || @getimagesize($tmp) === false) {
        return ['ok' => false, 'filename' => null, 'error' => 'QR image must be a PNG, JPG, or WEBP.'];
    }
    if (!is_dir(PAYMENT_QR_IMAGE_DIR) && !@mkdir(PAYMENT_QR_IMAGE_DIR, 0775, true) && !is_dir(PAYMENT_QR_IMAGE_DIR)) {
        return ['ok' => false, 'filename' => null, 'error' => 'QR image folder is not writable.'];
    }
    $filename = $channelKey . '-' . bin2hex(random_bytes(6)) . '.' . PAYMENT_PROOF_ALLOWED[$mime];
    $dest     = PAYMENT_QR_IMAGE_DIR . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmp, $dest)) {
        return ['ok' => false, 'filename' => null, 'error' => 'Could not save QR image.'];
    }
    return ['ok' => true, 'filename' => $filename, 'error' => null];
}

// ---------------------------------------------------------------------
// Handle save.
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } else {
        $updated = $channels;
        foreach (PAYMENT_CHANNELS as $key) {
            $updated[$key]['enabled']      = isset($_POST['enabled'][$key]);
            $updated[$key]['display_name'] = trim((string)($_POST['display_name'][$key] ?? '')) ?: paymentChannelTitle($key);
            $updated[$key]['account_name'] = trim((string)($_POST['account_name'][$key] ?? ''));
            $updated[$key]['account_no']   = trim((string)($_POST['account_no'][$key] ?? ''));

            // Optional new QR image for this channel.
            $hasFile = isset($_FILES['qr_image']['name'][$key]) && $_FILES['qr_image']['name'][$key] !== '';
            $upload = storeQrImage($hasFile
                ? [
                    'name'     => $_FILES['qr_image']['name'][$key],
                    'type'     => $_FILES['qr_image']['type'][$key],
                    'tmp_name' => $_FILES['qr_image']['tmp_name'][$key],
                    'error'    => $_FILES['qr_image']['error'][$key],
                    'size'     => $_FILES['qr_image']['size'][$key],
                  ]
                : ['error' => UPLOAD_ERR_NO_FILE], $key);

            if (!$upload['ok']) {
                $error = $upload['error'] ?? 'QR image upload failed.';
                break;
            }
            if ($upload['filename'] !== null) {
                $updated[$key]['qr_image'] = $upload['filename'];
            }
        }

        if ($error === '') {
            if (paymentSaveChannels($updated)) {
                logAudit('payment_settings_updated', 'config', null, 'QR payment channels updated');
                $success  = 'Payment settings saved.';
                $channels = $updated;
            } else {
                $error = 'Could not save settings. Check that /storage is writable.';
            }
        }
    }
}

include __DIR__ . '/../includes/header/header.php';
include __DIR__ . '/../includes/admin-sidebar.php';
?>
<div class="admin-container">
  <div class="mb-4">
    <h1 class="text-coffee-dark mb-2" style="font-size:2rem; font-weight:800;">
      <i class="fas fa-qrcode"></i> Payment Settings
    </h1>
    <p class="text-muted mb-0">Configure the QR channels customers see at checkout.</p>
  </div>

  <?php if ($error): ?>  <div class="alert alert-danger"><?php  echo htmlspecialchars($error, ENT_QUOTES); ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <?php echo csrfTokenField(); ?>
    <div class="row g-4">
      <?php foreach (PAYMENT_CHANNELS as $key): $ch = $channels[$key];
        $img = trim((string)($ch['qr_image'] ?? ''));
        $imgUrl = $img !== '' ? '../' . PAYMENT_QR_IMAGE_URLBASE . rawurlencode($img) : '';
      ?>
      <div class="col-md-4">
        <div class="admin-card h-100">
          <h2 class="h5 d-flex justify-content-between align-items-center">
            <?php echo htmlspecialchars(paymentChannelTitle($key), ENT_QUOTES); ?>
            <span class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" name="enabled[<?php echo $key; ?>]"
                     id="en_<?php echo $key; ?>" <?php echo !empty($ch['enabled']) ? 'checked' : ''; ?>>
              <label class="form-check-label small" for="en_<?php echo $key; ?>">Enabled</label>
            </span>
          </h2>

          <div class="mb-2">
            <label class="form-label small" for="dn_<?php echo $key; ?>">Display name</label>
            <input class="form-control form-control-sm" id="dn_<?php echo $key; ?>"
                   name="display_name[<?php echo $key; ?>]"
                   value="<?php echo htmlspecialchars((string)$ch['display_name'], ENT_QUOTES); ?>">
          </div>
          <div class="mb-2">
            <label class="form-label small" for="an_<?php echo $key; ?>">Account name</label>
            <input class="form-control form-control-sm" id="an_<?php echo $key; ?>"
                   name="account_name[<?php echo $key; ?>]"
                   value="<?php echo htmlspecialchars((string)$ch['account_name'], ENT_QUOTES); ?>">
          </div>
          <div class="mb-2">
            <label class="form-label small" for="ano_<?php echo $key; ?>">Account number</label>
            <input class="form-control form-control-sm" id="ano_<?php echo $key; ?>"
                   name="account_no[<?php echo $key; ?>]"
                   value="<?php echo htmlspecialchars((string)$ch['account_no'], ENT_QUOTES); ?>">
          </div>

          <?php // A bank settles by account transfer, so "no QR" is normal there,
                // not a missing setting. Say so instead of flagging it. ?>
          <div class="mb-2 text-center">
            <?php if ($imgUrl !== ''): ?>
              <img src="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars(paymentChannelTitle($key) . ' current QR', ENT_QUOTES); ?>"
                   style="max-width:140px; border:1px solid #eee; border-radius:8px;">
            <?php elseif (paymentChannelIsBank($key)): ?>
              <div class="text-muted small py-3">Customers transfer to the account number above — no QR needed.</div>
            <?php else: ?>
              <div class="text-muted small py-3">No QR image set</div>
            <?php endif; ?>
          </div>
          <div>
            <label class="form-label small" for="qr_<?php echo $key; ?>">
              <?php echo paymentChannelIsBank($key) ? 'QR image (optional)' : 'Replace QR image'; ?>
            </label>
            <input class="form-control form-control-sm" type="file" id="qr_<?php echo $key; ?>"
                   name="qr_image[<?php echo $key; ?>]" accept="image/png,image/jpeg,image/webp">
            <small class="text-muted">PNG/JPG/WEBP, max 3&nbsp;MB.</small>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <button type="submit" class="btn btn-dark mt-4"><i class="fas fa-save"></i> Save settings</button>
  </form>
</div>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->
<script src="../js/admin-sidebar.js"></script>
<?php include __DIR__ . '/../includes/footer/footer.php'; ?>
