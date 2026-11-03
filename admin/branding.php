<?php
declare(strict_types=1);

/**
 * Admin — Branding: store name, logos and app icon (favicon + installable app).
 *
 * Whoever owns the store can rebrand the whole system from here: every page,
 * email, invoice, the chatbot and the AI prompts read includes/brand.php.
 * Files go to uploads/branding/ (on the Railway volume). Each upload gets a
 * new random name, so browsers never show a cached old logo.
 */

require __DIR__ . '/../includes/config.php';

// --- Admin gate --------------------------------------------------------
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pageTitle = 'Branding';
$error     = '';
$success   = '';

const BRAND_UPLOAD_MAX_BYTES = 3 * 1024 * 1024; // 3 MB
const BRAND_UPLOAD_MAX_SIDE  = 4096;            // px; keeps GD memory sane
const BRAND_UPLOAD_ALLOWED   = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
// SVG is left out on purpose: it can carry scripts and GD cannot read it.

/**
 * Validate an uploaded image.
 *
 * @return array{ok:bool, tmp:?string, mime:?string, error:?string} tmp null = no file chosen
 */
function brandCheckUpload(string $field, string $label): array
{
    $file = $_FILES[$field] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'tmp' => null, 'mime' => null, 'error' => null];
    }
    $fail = static fn(string $msg): array => ['ok' => false, 'tmp' => null, 'mime' => null, 'error' => $msg];
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return $fail("$label must be under 3 MB.");
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return $fail("$label upload failed. Please try again.");
    }
    if (($file['size'] ?? 0) <= 0 || $file['size'] > BRAND_UPLOAD_MAX_BYTES) {
        return $fail("$label must be under 3 MB.");
    }
    $tmp = $file['tmp_name'] ?? '';
    if (!is_string($tmp) || !is_uploaded_file($tmp)) {
        return $fail("Invalid $label upload.");
    }
    $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $size = @getimagesize($tmp);
    if (!isset(BRAND_UPLOAD_ALLOWED[$mime]) || $size === false) {
        return $fail("$label must be a PNG, JPG or WEBP image.");
    }
    if ($size[0] > BRAND_UPLOAD_MAX_SIDE || $size[1] > BRAND_UPLOAD_MAX_SIDE) {
        return $fail("$label is too large (max 4096 × 4096 px).");
    }
    return ['ok' => true, 'tmp' => $tmp, 'mime' => $mime, 'error' => null];
}

/** Move a checked upload into uploads/branding/ under a fresh name. */
function brandStoreUpload(string $tmp, string $mime, string $prefix): ?string
{
    $name = $prefix . '-' . bin2hex(random_bytes(6)) . '.' . BRAND_UPLOAD_ALLOWED[$mime];
    return move_uploaded_file($tmp, BRAND_DIR . DIRECTORY_SEPARATOR . $name) ? $name : null;
}

/**
 * Build the favicon / app icon set from one image: 32 (browser tab),
 * 180 (iPhone home screen), 192 + 512 (installable app) and a 512 maskable
 * one (Android crops it to a circle, so the art sits in the safe centre).
 *
 * @return string|null new icon version id, or null on failure
 */
function brandMakeIcons(string $path, string $mime): ?string
{
    if (!function_exists('imagecreatetruecolor')) {
        return null;
    }
    $src = match ($mime) {
        'image/png'  => @imagecreatefrompng($path),
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default      => false,
    };
    if (!$src) {
        return null;
    }
    $sw = imagesx($src);
    $sh = imagesy($src);
    $version = bin2hex(random_bytes(6));

    // size key => [pixels, share of the canvas the art fills, opaque background]
    // iOS and Android masks show a black/odd fill behind transparency, so those get white.
    $specs = [
        '32'       => [32, 1.0, false],
        '180'      => [180, 0.82, true],
        '192'      => [192, 0.92, false],
        '512'      => [512, 0.92, false],
        'maskable' => [512, 0.62, true],
    ];
    $ok = true;
    foreach ($specs as $key => [$px, $fill, $opaque]) {
        $dst = imagecreatetruecolor($px, $px);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, $opaque
            ? imagecolorallocate($dst, 255, 255, 255)
            : imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagealphablending($dst, true);

        // Fit the whole image inside the box, keeping its proportions.
        $box   = $px * $fill;
        $scale = min($box / $sw, $box / $sh);
        $w     = max(1, (int)round($sw * $scale));
        $h     = max(1, (int)round($sh * $scale));
        imagecopyresampled($dst, $src, (int)(($px - $w) / 2), (int)(($px - $h) / 2), 0, 0, $w, $h, $sw, $sh);

        $file = BRAND_DIR . DIRECTORY_SEPARATOR . 'icon-' . $version . '-' . $key . '.png';
        $ok = imagepng($dst, $file, 9) && $ok;
        imagedestroy($dst);
        if (!$ok) {
            break;
        }
    }
    imagedestroy($src);
    if (!$ok) {
        brandDeleteFiles('icon-' . $version . '-');
        return null;
    }
    return $version;
}

/** Delete branding files whose name starts with $prefix (logo-, icon-...). */
function brandDeleteFiles(string $prefix): void
{
    foreach (glob(BRAND_DIR . DIRECTORY_SEPARATOR . $prefix . '*') ?: [] as $f) {
        @unlink($f);
    }
}

/** Remove uploads no longer referenced by $settings (old logos and icon sets). */
function brandCleanup(array $settings): void
{
    $keep = array_filter([$settings['logo'], $settings['logo_dark']]);
    // No GLOB_BRACE: it is missing on some platforms. brand.json is never matched.
    foreach (glob(BRAND_DIR . DIRECTORY_SEPARATOR . '*-*') ?: [] as $f) {
        $name = basename($f);
        if (!preg_match('/^(logo|logodark|iconsrc|icon)-/', $name) || in_array($name, $keep, true)) {
            continue;
        }
        if ($settings['icon'] !== '' && strpos($name, 'icon-' . $settings['icon'] . '-') === 0) {
            continue;
        }
        @unlink($f);
    }
}

// ---------------------------------------------------------------------
// Handle save / reset.
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'reset') {
        if (brandSave(BRAND_DEFAULTS)) {
            brandCleanup(BRAND_DEFAULTS);
            logAudit('branding_reset', 'config', null, 'Branding reset to the default Thread & Press Hub brand');
            $success = 'Branding reset to the original name, logo and icons.';
        } else {
            $error = 'Could not save. Check that the uploads folder is writable.';
        }
    } else {
        $settings = brandSettings();
        $new = [
            'name'       => trim((string)($_POST['name'] ?? '')),
            'short_name' => trim((string)($_POST['short_name'] ?? '')),
            'tagline'    => trim((string)($_POST['tagline'] ?? '')),
        ];

        if ($new['name'] === '' || $new['short_name'] === '') {
            $error = 'Store name and short name are required.';
        } elseif (mb_strlen($new['name']) > 60) {
            $error = 'Store name must be 60 characters or fewer.';
        } elseif (mb_strlen($new['short_name']) > 24) {
            $error = 'Short name must be 24 characters or fewer (it has to fit the navbar and a phone home screen).';
        } elseif (mb_strlen($new['tagline']) > 16) {
            $error = 'Tagline must be 16 characters or fewer.';
        } elseif (preg_match('/[\x00-\x1F\x7F]/u', implode('', $new)) || !mb_check_encoding(implode('', $new), 'UTF-8')) {
            $error = 'Names cannot contain line breaks or special control characters.';
        }

        $uploads = [];
        if ($error === '') {
            foreach (['logo' => 'Logo', 'logo_dark' => 'Dark-background logo', 'icon' => 'App icon'] as $field => $label) {
                $check = brandCheckUpload($field, $label);
                if (!$check['ok']) {
                    $error = (string)$check['error'];
                    break;
                }
                if ($check['tmp'] !== null) {
                    $uploads[$field] = $check;
                }
            }
        }
        if ($error === '' && $uploads && !is_dir(BRAND_DIR) && !@mkdir(BRAND_DIR, 0775, true) && !is_dir(BRAND_DIR)) {
            $error = 'The uploads/branding folder is not writable.';
        }

        if ($error === '') {
            $settings = array_merge($settings, $new);
            if (isset($_POST['remove_logo_dark']) && !isset($uploads['logo_dark'])) {
                $settings['logo_dark'] = '';
            }
            $prefixes = ['logo' => 'logo', 'logo_dark' => 'logodark', 'icon' => 'iconsrc'];
            $iconSource = null;
            foreach ($uploads as $field => $u) {
                $stored = brandStoreUpload((string)$u['tmp'], (string)$u['mime'], $prefixes[$field]);
                if ($stored === null) {
                    $error = 'Could not save the uploaded image.';
                    break;
                }
                if ($field === 'icon') {
                    $iconSource = [$stored, $u['mime']];
                } else {
                    $settings[$field] = $stored;
                }
            }
            // A new logo with no icon of its own becomes the icon too, so the
            // browser tab and phone home screen never keep the old brand.
            if ($error === '' && $iconSource === null && isset($uploads['logo'])) {
                $iconSource = [$settings['logo'], $uploads['logo']['mime']];
            }
            if ($error === '' && $iconSource !== null) {
                $version = brandMakeIcons(BRAND_DIR . DIRECTORY_SEPARATOR . $iconSource[0], (string)$iconSource[1]);
                if ($version === null) {
                    $error = 'Could not build the app icons from that image (the PHP GD extension is needed).';
                } else {
                    $settings['icon'] = $version;
                }
            }
        }

        if ($error === '') {
            if (brandSave($settings)) {
                brandCleanup($settings);
                logAudit('branding_updated', 'config', null, 'Branding updated: ' . $settings['name']);
                $success = 'Branding saved. The whole store now uses it.';
            } else {
                $error = 'Could not save. Check that the uploads folder is writable.';
            }
        }
        if ($error !== '') {
            // Drop files written in this failed attempt; brand.json was not changed.
            brandCleanup(brandSettings());
        }
    }
}

$s = brandSettings();
include __DIR__ . '/../includes/header/header.php';
include __DIR__ . '/../includes/admin-sidebar.php';
?>
<div class="admin-container">
  <div class="mb-4">
    <h1 class="text-coffee-dark mb-2" style="font-size:2rem; font-weight:800;">
      <i class="fas fa-swatchbook"></i> Branding
    </h1>
    <p class="text-muted mb-0">The store name, logo and app icon used everywhere: website, sign-in pages, emails, invoices, chatbot and the installed app.</p>
  </div>

  <?php if ($error): ?>  <div class="alert alert-danger"><?php  echo htmlspecialchars($error, ENT_QUOTES); ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <?php echo csrfTokenField(); ?>
    <div class="row g-4">
      <div class="col-lg-6">
        <div class="admin-card h-100">
          <h2 class="h5">Name</h2>
          <div class="mb-3">
            <label class="form-label small" for="b_name">Store name</label>
            <input class="form-control" id="b_name" name="name" maxlength="60" required
                   value="<?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?>">
            <small class="text-muted">Page titles, emails, invoices, chatbot, footer.</small>
          </div>
          <div class="mb-3">
            <label class="form-label small" for="b_short">Short name</label>
            <input class="form-control" id="b_short" name="short_name" maxlength="24" required
                   value="<?php echo htmlspecialchars($s['short_name'], ENT_QUOTES); ?>">
            <small class="text-muted">Navbar, admin sidebar and the app name on a phone. Max 24 characters.</small>
          </div>
          <div class="mb-3">
            <label class="form-label small" for="b_tag">Tagline (optional)</label>
            <input class="form-control" id="b_tag" name="tagline" maxlength="16"
                   value="<?php echo htmlspecialchars($s['tagline'], ENT_QUOTES); ?>">
            <small class="text-muted">Small text under the navbar name, e.g. "HUB". Leave blank for none.</small>
          </div>
          <p class="small text-muted mb-0">
            <i class="fas fa-circle-info"></i> Payment account names are set separately in Payment Settings,
            because they must match the real bank or e-wallet account.
          </p>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="admin-card h-100">
          <h2 class="h5">Logo</h2>
          <div class="d-flex gap-3 mb-3 flex-wrap">
            <div class="text-center">
              <div class="brand-preview" style="background:#fff;"><img src="../<?php echo htmlspecialchars(brandLogoUrl(false), ENT_QUOTES); ?>" alt="Current logo"></div>
              <small class="text-muted">Light backgrounds</small>
            </div>
            <div class="text-center">
              <div class="brand-preview" style="background:#131313;"><img src="../<?php echo htmlspecialchars(brandLogoUrl(true), ENT_QUOTES); ?>" alt="Current dark mode logo"></div>
              <small class="text-muted">Dark backgrounds</small>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small" for="b_logo">Replace logo</label>
            <input class="form-control form-control-sm" type="file" id="b_logo" name="logo" accept="image/png,image/jpeg,image/webp">
            <small class="text-muted">For light backgrounds: sign-in pages, footer, emails. PNG with a transparent background works best. Max 3&nbsp;MB.</small>
          </div>
          <div class="mb-2">
            <label class="form-label small" for="b_logo_dark">Logo for dark backgrounds (recommended)</label>
            <input class="form-control form-control-sm" type="file" id="b_logo_dark" name="logo_dark" accept="image/png,image/jpeg,image/webp">
            <small class="text-muted">A white or light-coloured version. Used on the navbar and admin sidebar (always dark) and in dark mode. Without one, the logo above is used there too.</small>
          </div>
          <?php if ($s['logo_dark'] !== ''): ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="b_rm_dark" name="remove_logo_dark">
            <label class="form-check-label small" for="b_rm_dark">Remove the dark-background logo</label>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-12">
        <div class="admin-card">
          <h2 class="h5">App icon &amp; favicon</h2>
          <div class="d-flex align-items-end gap-4 flex-wrap mb-3">
            <div class="text-center">
              <img src="../<?php echo htmlspecialchars(brandIconUrl('32'), ENT_QUOTES); ?>" width="32" height="32" alt="Browser tab icon" style="object-fit:contain;">
              <div><small class="text-muted">Browser tab</small></div>
            </div>
            <div class="text-center">
              <img src="../<?php echo htmlspecialchars(brandIconUrl('180'), ENT_QUOTES); ?>" width="60" height="60" alt="iPhone icon" style="border-radius:14px; object-fit:contain;">
              <div><small class="text-muted">iPhone</small></div>
            </div>
            <div class="text-center">
              <img src="../<?php echo htmlspecialchars(brandIconUrl('maskable'), ENT_QUOTES); ?>" width="60" height="60" alt="Android icon" style="border-radius:50%; object-fit:contain;">
              <div><small class="text-muted">Android</small></div>
            </div>
          </div>
          <label class="form-label small" for="b_icon">Replace app icon (optional)</label>
          <input class="form-control form-control-sm" type="file" id="b_icon" name="icon" accept="image/png,image/jpeg,image/webp" style="max-width:420px;">
          <small class="text-muted d-block">A square image, at least 512 × 512 px. All sizes are made from it automatically.
            If you only upload a new logo, the icons are made from the logo.</small>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mt-4 flex-wrap">
      <button type="submit" class="btn btn-dark"><i class="fas fa-save"></i> Save branding</button>
    </div>
  </form>

  <form method="POST" class="mt-3"
        onsubmit="return confirm('Reset to the original Thread & Press Hub name, logo and icons? Uploaded files will be deleted.');">
    <?php echo csrfTokenField(); ?>
    <input type="hidden" name="action" value="reset">
    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fas fa-rotate-left"></i> Reset to original brand</button>
  </form>
</div>
<style>
.brand-preview { width: 120px; height: 120px; border-radius: 12px; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center; padding: 10px; margin-bottom: 4px; }
.brand-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
</style>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->
<script src="../js/admin-sidebar.js"></script>
<?php include __DIR__ . '/../includes/footer/footer.php'; ?>
