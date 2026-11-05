<?php
declare(strict_types=1);

/**
 * Admin — Exhibit Mode: names the demo account that visitors share on a booth
 * device. See includes/exhibit.php for what changes for that account.
 */

require __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pageTitle = 'Exhibit Mode';
$error     = '';
$success   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'off') {
        if (exhibitOff()) {
            logAudit('exhibit_mode_off', 'config', null, 'Exhibit mode turned off');
            $success = 'Exhibit mode is off. The demo account is a normal account again.';
        } else {
            $error = 'Could not turn it off. Check that the storage folder is writable.';
        }
    } elseif (($_POST['action'] ?? '') === 'sample_add') {
        if (empty($_POST['permission'])) {
            $error = 'Confirm that the person in the photos gave permission.';
        } else {
            $why = exhibitSampleAdd($_FILES['before'] ?? null, $_FILES['after'] ?? null);
            if ($why !== null) {
                $error = $why;
            } else {
                logAudit('exhibit_sample_added', 'config', null, 'Sample look added');
                $success = 'Sample look added.';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'sample_delete') {
        if (exhibitSampleDelete((string) ($_POST['id'] ?? ''))) {
            logAudit('exhibit_sample_deleted', 'config', null, 'Sample look deleted');
            $success = 'Sample look deleted.';
        } else {
            $error = 'That sample is already gone.';
        }
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $limit = $_POST['limit'] ?? '';
        $stmt  = $conn->prepare('SELECT id, user_type FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $limitError = exhibitLimitError($limit);
        if (!$user) {
            $error = 'No account uses that email.';
        } elseif ($user['user_type'] === 'admin') {
            $error = 'Use a separate demo account. An admin account opens the admin pages to visitors.';
        } elseif ($limitError !== null) {
            $error = $limitError;
        } elseif (exhibitSave((int) $user['id'], (int) $limit)) {
            logAudit('exhibit_mode_on', 'config', (int) $user['id'], 'Exhibit mode on, ' . (int) $limit . ' AI uses per hour');
            $success = 'Exhibit mode is on for that account.';
        } else {
            $error = 'Could not save. Check that the storage folder is writable.';
        }
    }
}

$settings    = exhibitSettings();
$demoEmail   = '';
if ($settings['user_id'] > 0) {
    $stmt = $conn->prepare('SELECT email FROM users WHERE id = ?');
    $stmt->bind_param('i', $settings['user_id']);
    $stmt->execute();
    $demoEmail = (string) ($stmt->get_result()->fetch_row()[0] ?? '');
    $stmt->close();
}
$postedSettings = $error !== '' && isset($_POST['email']);   // bad input: show what was typed
$showEmail = $postedSettings ? (string) $_POST['email'] : $demoEmail;
$showLimit = $postedSettings ? (string) ($_POST['limit'] ?? '') : (string) $settings['limit'];
$samples   = exhibitSamples();

include __DIR__ . '/../includes/header/header.php';
include __DIR__ . '/../includes/admin-sidebar.php';
?>
<div class="admin-container">
  <div class="mb-4">
    <h1 class="text-coffee-dark mb-2" style="font-size:2rem; font-weight:800;">
      <i class="fas fa-display"></i> Exhibit Mode
    </h1>
    <p class="text-muted mb-0">For one demo account that visitors share on a booth device.</p>
  </div>

  <?php if ($error): ?>  <div class="alert alert-danger"><?php  echo htmlspecialchars($error, ENT_QUOTES); ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div><?php endif; ?>

  <div class="admin-card mb-3">
    <p class="mb-2">
      <span class="badge bg-<?php echo $settings['user_id'] > 0 ? 'success' : 'secondary'; ?>"><?php echo $settings['user_id'] > 0 ? 'On' : 'Off'; ?></span>
      <?php if ($settings['user_id'] > 0): ?>
        <?php echo htmlspecialchars($demoEmail !== '' ? $demoEmail : 'That account no longer exists', ENT_QUOTES); ?>,
        <?php echo (int) $settings['limit']; ?> uses per hour for each AI feature
      <?php endif; ?>
    </p>
    <p class="small text-muted mb-3">On that account's Virtual Try-On: the hourly AI limit goes up, Save look and Download are hidden,
      every visitor agrees to the photo notice, and <strong>New Visitor</strong> clears what the last person left in the browser.</p>

    <form method="POST">
      <?php echo csrfTokenField(); ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label small fw-bold" for="exEmail">Demo account email *</label>
          <input type="email" class="form-control" id="exEmail" name="email" required maxlength="255"
                 value="<?php echo htmlspecialchars($showEmail, ENT_QUOTES); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold" for="exLimit">AI uses per hour *</label>
          <input type="number" class="form-control" id="exLimit" name="limit" required step="1"
                 min="<?php echo EXHIBIT_LIMIT_MIN; ?>" max="<?php echo EXHIBIT_LIMIT_MAX; ?>"
                 value="<?php echo htmlspecialchars($showLimit, ENT_QUOTES); ?>">
        </div>
      </div>
      <p class="small text-muted mt-3 mb-0"><i class="fas fa-circle-info"></i> Each try-on costs AI credits, so keep the limit close to what the booth can actually do. Normal accounts stay at 15 try-ons per hour.</p>
      <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-dark"><i class="fas fa-save"></i> Save</button>
      </div>
    </form>
  </div>

  <?php if ($settings['user_id'] > 0): ?>
  <form method="POST" onsubmit="return confirm('Turn exhibit mode off?');">
    <?php echo csrfTokenField(); ?>
    <input type="hidden" name="action" value="off">
    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fas fa-power-off"></i> Turn off after the exhibit</button>
  </form>
  <?php endif; ?>

  <div class="admin-card mt-4">
    <h2 class="h5 mb-1">Sample looks (<?php echo count($samples); ?> of <?php echo EXHIBIT_SAMPLES_MAX; ?>)</h2>
    <p class="small text-muted mb-3">Before and after photos the booth can show when the AI cannot run, such as when the quota runs out or the internet drops. Only the demo account sees them.</p>

    <?php if ($samples): ?>
    <div class="row g-3 mb-4">
      <?php foreach ($samples as $i => $s): ?>
      <div class="col-md-6">
        <div class="d-flex gap-2 align-items-start">
          <img src="../<?php echo htmlspecialchars($s['before'], ENT_QUOTES); ?>" alt="Sample <?php echo $i + 1; ?>, before" style="width:40%; aspect-ratio:3/4; object-fit:cover; border-radius:8px;">
          <img src="../<?php echo htmlspecialchars($s['after'], ENT_QUOTES); ?>" alt="Sample <?php echo $i + 1; ?>, after" style="width:40%; aspect-ratio:3/4; object-fit:cover; border-radius:8px;">
          <form method="POST" onsubmit="return confirm('Delete this sample look?');">
            <?php echo csrfTokenField(); ?>
            <input type="hidden" name="action" value="sample_delete">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($s['id'], ENT_QUOTES); ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete sample <?php echo $i + 1; ?>"><i class="fas fa-trash"></i></button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (count($samples) < EXHIBIT_SAMPLES_MAX): ?>
    <form method="POST" enctype="multipart/form-data" id="sampleForm">
      <?php echo csrfTokenField(); ?>
      <input type="hidden" name="action" value="sample_add">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label small fw-bold" for="sampleBefore">Before photo *</label>
          <input type="file" class="form-control" id="sampleBefore" name="before" accept="image/jpeg,image/png" required>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-bold" for="sampleAfter">After photo (the try-on result) *</label>
          <input type="file" class="form-control" id="sampleAfter" name="after" accept="image/jpeg,image/png" required>
        </div>
      </div>
      <div class="form-check mt-3">
        <input class="form-check-input" type="checkbox" id="samplePermission" name="permission" value="1" required>
        <label class="form-check-label small" for="samplePermission">The person in these photos agreed to have them shown at the exhibit.</label>
      </div>
      <button type="submit" class="btn btn-dark mt-3" id="sampleSubmit"><i class="fas fa-upload"></i> Add sample look</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<script>
// Redraws each photo before it is sent: the browser turns it the right way up and
// makes it smaller, and the copy carries no location or device data. The server
// also strips that data, so this is only what keeps phone photos from going sideways.
(function () {
    const form = document.getElementById('sampleForm');
    if (!form) return;
    const MAX_SIDE = 1200;
    async function redraw(input) {
        const file = input.files[0];
        if (!file) return;
        const url = URL.createObjectURL(file);
        try {
            const img = new Image();
            await new Promise((ok, fail) => { img.onload = ok; img.onerror = fail; img.src = url; });
            const k = Math.min(1, MAX_SIDE / Math.max(img.naturalWidth, img.naturalHeight));
            const cv = document.createElement('canvas');
            cv.width = Math.round(img.naturalWidth * k);
            cv.height = Math.round(img.naturalHeight * k);
            cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height);
            const blob = await new Promise((ok) => cv.toBlob(ok, 'image/jpeg', 0.9));
            if (!blob) return;
            const box = new DataTransfer();
            box.items.add(new File([blob], 'sample.jpg', { type: 'image/jpeg' }));
            input.files = box.files;
        } catch (e) {
            // Sent as it is; the server still checks it and strips the data.
        } finally {
            URL.revokeObjectURL(url);
        }
    }
    let ready = false;
    form.addEventListener('submit', async (e) => {
        if (ready) return;
        e.preventDefault();
        document.getElementById('sampleSubmit').disabled = true;
        await redraw(document.getElementById('sampleBefore'));
        await redraw(document.getElementById('sampleAfter'));
        ready = true;
        form.submit();
    });
})();
</script>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->
<script src="../js/admin-sidebar.js"></script>
<?php include __DIR__ . '/../includes/footer/footer.php'; ?>
