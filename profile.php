<?php
require 'includes/config.php';
require_once 'includes/addresses.php';   // saved delivery addresses (max 3)
redirectToLogin();

$pageTitle = 'My Profile';
$error = '';
$success = '';

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ---------------------------------------------------------------------
// Saved addresses. These live in their own <form>s (HTML forbids nesting),
// so they are handled first and the profile branch below is skipped.
// ---------------------------------------------------------------------
$addrError = '';
$addrSuccess = '';
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['address_action'])) {
    $uid = (int) $_SESSION['user_id'];
    if (!verifyCsrfToken()) {
        $addrError = 'Invalid form submission. Please try again.';
    } else {
        $fields = [
            'label'          => sanitizeInput($_POST['label'] ?? ''),
            'street_address' => sanitizeInput($_POST['street_address'] ?? ''),
            'barangay'       => sanitizeInput($_POST['barangay'] ?? ''),
            'city'           => sanitizeInput($_POST['city'] ?? ''),
            'province'       => sanitizeInput($_POST['province'] ?? ''),
            'zipcode'        => sanitizeInput($_POST['zipcode'] ?? ''),
        ];
        $targetId = (int) ($_POST['address_id'] ?? 0);

        switch ($_POST['address_action']) {
            case 'add':
                $r = addressAdd($uid, $fields);
                $addrError = $r['ok'] ? '' : $r['error'];
                $addrSuccess = $r['ok'] ? 'Address saved.' : '';
                break;
            case 'update':
                $r = addressUpdate($uid, $targetId, $fields);
                $addrError = $r['ok'] ? '' : $r['error'];
                $addrSuccess = $r['ok'] ? 'Address updated.' : '';
                if ($r['ok']) { $editingId = 0; }
                break;
            case 'delete':
                $r = addressDelete($uid, $targetId);
                $addrError = $r['ok'] ? '' : $r['error'];
                $addrSuccess = $r['ok'] ? 'Address removed.' : '';
                break;
            case 'default':
                $r = addressSetDefault($uid, $targetId);
                $addrError = $r['ok'] ? '' : $r['error'];
                $addrSuccess = $r['ok'] ? 'Default address updated.' : '';
                break;
        }
        // users.* mirrors the default address, so re-read the row the page prints.
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['address_action'])) {
    $fullname = sanitizeInput($_POST['fullname'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    if (!verifyCsrfToken()) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (empty($fullname)) {
        $error = 'Full name cannot be empty!';
    } else {
        // Address columns are NOT touched here: they mirror the default saved
        // address and are managed by the address cards below. Including them
        // would wipe the saved address every time the profile is saved.
        $update_stmt = $conn->prepare("UPDATE users SET fullname = ?, phone = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $fullname, $phone, $_SESSION['user_id']);
        
        if ($update_stmt->execute()) {
            $_SESSION['user_name'] = $fullname;
            $success = 'Profile updated successfully!';
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $error = 'Failed to update profile!';
        }
        $update_stmt->close();

        // Change password if provided
        if (!empty($new_password)) {
            if (!verifyPassword($current_password, $user['password'])) {
                $error = 'Current password is incorrect!';
            } elseif (strlen($new_password) < 6) {
                $error = 'New password must be at least 6 characters!';
            } else {
                $hashed = hashPassword($new_password);
                $pwd_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $pwd_stmt->bind_param("si", $hashed, $_SESSION['user_id']);
                
                if ($pwd_stmt->execute()) {
                    $success = 'Password changed successfully!';
                } else {
                    $error = 'Failed to change password!';
                }
                $pwd_stmt->close();
            }
        }
    }
}
?>

<?php include 'includes/header/header.php'; ?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb" style="font-size:0.85rem;">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active">Profile</li>
        </ol>
    </nav>
    <h1 style="font-weight:800; font-size:2rem; margin-bottom:1.5rem;">My Profile</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="border-radius:12px; border:none; font-size:0.9rem;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success" style="border-radius:12px; border:none; font-size:0.9rem;"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-8">
            <div class="card border-0" style="border:1px solid var(--border-light); border-radius:var(--radius-lg);">
                <div class="card-body p-4">
                    <h5 style="font-weight:700; margin-bottom:1.25rem;">Update Profile</h5>
                    <form method="POST">
                        <?php echo csrfTokenField(); ?>
                        <div class="form-group mb-3">
                            <label class="form-label" style="font-size:0.82rem; font-weight:600;">Full Name</label>
                            <input type="text" class="form-control" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label" style="font-size:0.82rem; font-weight:600;">Email (Cannot be changed)</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="background:var(--bg-light);">
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label" style="font-size:0.82rem; font-weight:600;">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label" style="font-size:0.82rem; font-weight:600;">Account Type</label>
                            <input type="text" class="form-control" value="<?php echo ucfirst(str_replace('_', ' ', $user['user_type'])); ?>" disabled style="background:var(--bg-light);">
                        </div>

                        <hr style="border-color:var(--border-light);">

                        <h6 style="font-weight:700; margin-bottom:1rem;">Change Password</h6>

                        <div class="form-group mb-3">
                            <label class="form-label" style="font-size:0.82rem; font-weight:600;">Current Password</label>
                            <input type="password" class="form-control" name="current_password">
                            <small class="text-muted" style="font-size:0.78rem;">Leave empty if you don't want to change password</small>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label" style="font-size:0.82rem; font-weight:600;">New Password</label>
                            <input type="password" class="form-control" name="new_password">
                        </div>

                        <button type="submit" class="btn btn-dark" style="border-radius:12px; font-weight:600; padding:0.65rem 1.75rem;">
                            Save Changes
                        </button>
                    </form>
                </div>
            </div>

            <?php
            $myAddresses = addressList((int) $_SESSION['user_id']);
            $canAddMore  = addressCanAdd((int) $_SESSION['user_id']);
            $editing     = $editingId > 0 ? addressGet((int) $_SESSION['user_id'], $editingId) : null;
            ?>
            <div class="card border-0 mt-3" id="addresses" style="border:1px solid var(--border-light); border-radius:var(--radius-lg);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-1 flex-wrap gap-2">
                        <h6 style="font-weight:700; margin:0;">
                            <i class="fas fa-map-marker-alt me-1" style="color:var(--accent-green, #2d6a4f);"></i>
                            Delivery Addresses
                        </h6>
                        <span class="badge" style="background:var(--bg-light); color:var(--text-dark); font-weight:600;">
                            <?php echo count($myAddresses); ?> of <?php echo ADDRESS_MAX_PER_USER; ?>
                        </span>
                    </div>
                    <p style="font-size:0.78rem; color:#888; margin-bottom:1rem;">
                        Save up to <?php echo ADDRESS_MAX_PER_USER; ?>. You choose which one to ship to at checkout,
                        and the shipping fee follows the address you pick.
                    </p>

                    <?php if ($addrError): ?>
                        <div class="alert alert-danger py-2" style="font-size:0.85rem;"><?php echo htmlspecialchars($addrError, ENT_QUOTES); ?></div>
                    <?php endif; ?>
                    <?php if ($addrSuccess): ?>
                        <div class="alert alert-success py-2" style="font-size:0.85rem;"><?php echo htmlspecialchars($addrSuccess, ENT_QUOTES); ?></div>
                    <?php endif; ?>

                    <?php if ($myAddresses === []): ?>
                        <p class="text-muted" style="font-size:0.88rem;">No saved addresses yet. Add one below.</p>
                    <?php endif; ?>

                    <?php foreach ($myAddresses as $addr): ?>
                        <?php $isDef = !empty($addr['is_default']); ?>
                        <div style="border:1px solid <?php echo $isDef ? 'var(--accent-green, #2d6a4f)' : 'var(--border-light, #e9ecef)'; ?>; border-radius:12px; padding:1rem; margin-bottom:0.75rem;">
                            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                <div>
                                    <strong style="font-size:0.9rem;"><?php echo htmlspecialchars((string) $addr['label']); ?></strong>
                                    <?php if ($isDef): ?>
                                        <span class="badge bg-success" style="font-size:0.68rem;">Default</span>
                                    <?php endif; ?>
                                    <p style="margin:0.3rem 0 0; font-size:0.85rem; color:#555;">
                                        <?php echo htmlspecialchars(addressFormat($addr)); ?>
                                    </p>
                                </div>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a class="btn btn-sm btn-outline-dark" style="border-radius:8px; font-size:0.75rem;"
                                       href="profile.php?edit=<?php echo (int) $addr['id']; ?>#addresses">Edit</a>
                                    <?php if (!$isDef): ?>
                                        <form method="POST" class="d-inline">
                                            <?php echo csrfTokenField(); ?>
                                            <input type="hidden" name="address_action" value="default">
                                            <input type="hidden" name="address_id" value="<?php echo (int) $addr['id']; ?>">
                                            <button class="btn btn-sm btn-outline-dark" style="border-radius:8px; font-size:0.75rem;">Make default</button>
                                        </form>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Remove this address?');">
                                            <?php echo csrfTokenField(); ?>
                                            <input type="hidden" name="address_action" value="delete">
                                            <input type="hidden" name="address_id" value="<?php echo (int) $addr['id']; ?>">
                                            <button class="btn btn-sm btn-outline-danger" style="border-radius:8px; font-size:0.75rem;">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php // The default has no Delete button on purpose: promote another one first. ?>
                    <?php if (count($myAddresses) > 1): ?>
                        <p class="text-muted" style="font-size:0.75rem;">To delete your default address, make another one the default first.</p>
                    <?php endif; ?>

                    <?php if ($editing !== null): ?>
                        <hr style="border-color:var(--border-light);">
                        <h6 style="font-weight:700; margin-bottom:1rem;">Edit address</h6>
                        <form method="POST">
                            <?php echo csrfTokenField(); ?>
                            <input type="hidden" name="address_action" value="update">
                            <input type="hidden" name="address_id" value="<?php echo (int) $editing['id']; ?>">
                            <?php $v = $editing; include __DIR__ . '/includes/address-fields.php'; ?>
                            <button type="submit" class="btn btn-dark" style="border-radius:12px; font-weight:600; padding:0.5rem 1.5rem;">Save address</button>
                            <a href="profile.php#addresses" class="btn btn-link" style="font-weight:600;">Cancel</a>
                        </form>
                    <?php elseif ($canAddMore): ?>
                        <hr style="border-color:var(--border-light);">
                        <h6 style="font-weight:700; margin-bottom:1rem;">Add an address</h6>
                        <form method="POST">
                            <?php echo csrfTokenField(); ?>
                            <input type="hidden" name="address_action" value="add">
                            <?php $v = []; include __DIR__ . '/includes/address-fields.php'; ?>
                            <button type="submit" class="btn btn-dark" style="border-radius:12px; font-weight:600; padding:0.5rem 1.5rem;">Add address</button>
                        </form>
                    <?php else: ?>
                        <hr style="border-color:var(--border-light);">
                        <p class="text-muted" style="font-size:0.85rem; margin:0;">
                            You have reached the limit of <?php echo ADDRESS_MAX_PER_USER; ?> saved addresses. Delete one to add another.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 mb-3" style="border:1px solid var(--border-light); border-radius:var(--radius-lg);">
                <div class="card-body p-4">
                    <h6 style="font-weight:700; margin-bottom:1rem;">Account Information</h6>
                    <dl class="row" style="font-size:0.88rem; margin-bottom:0;">
                        <dt class="col-sm-5 text-muted fw-normal">Member Since</dt>
                        <dd class="col-sm-7"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></dd>

                        <dt class="col-sm-5 text-muted fw-normal">Account Type</dt>
                        <dd class="col-sm-7">
                            <span class="badge" style="background:var(--bg-light); color:var(--text-dark); font-weight:600;">
                                <?php echo ucfirst(str_replace('_', ' ', $user['user_type'])); ?>
                            </span>
                        </dd>
                    </dl>

                    <?php if ($user['user_type'] === 'pwd' && $user['pwd_id']): ?>
                    <div class="mt-2">
                        <small class="text-muted">PWD ID: <?php echo htmlspecialchars($user['pwd_id']); ?></small>
                    </div>
                    <?php endif; ?>

                    <?php if ($user['user_type'] === 'senior' && $user['senior_id']): ?>
                    <div class="mt-2">
                        <small class="text-muted">Senior ID: <?php echo htmlspecialchars($user['senior_id']); ?></small>
                    </div>
                    <?php endif; ?>

                    <div class="mt-3 pt-3" style="border-top:1px solid var(--border-light);">
                        <a href="logout.php" class="btn btn-outline-dark btn-sm w-100" style="border-radius:8px;">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>

            <div class="card border-0" style="border:1px solid var(--border-light); border-radius:var(--radius-lg);">
                <div class="card-body p-4">
                    <h6 style="font-weight:700; margin-bottom:1rem;"><i class="fas fa-truck me-1" style="color:var(--accent-green, #2d6a4f);"></i> Saved Address</h6>
                    <?php if (!empty($user['street_address'])): ?>
                        <p style="font-size:0.88rem; margin-bottom:0.25rem;"><?php echo htmlspecialchars($user['street_address']); ?></p>
                        <p style="font-size:0.85rem; color:#666; margin-bottom:0.25rem;">
                            <?php echo htmlspecialchars($user['barangay']); ?>, <?php echo htmlspecialchars($user['city']); ?>
                        </p>
                        <p style="font-size:0.85rem; color:#666; margin-bottom:0;">
                            <?php echo htmlspecialchars($user['province']); ?> <?php echo htmlspecialchars($user['zipcode']); ?>
                        </p>
                    <?php else: ?>
                        <p style="font-size:0.85rem; color:#aaa; margin-bottom:0;"><i class="fas fa-info-circle me-1"></i> No address saved yet. Fill in the Delivery Address form to save.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 mt-3" style="border:1px solid var(--border-light); border-radius:var(--radius-lg);">
                <div class="card-body p-4">
                    <h6 style="font-weight:700; margin-bottom:1rem;">Your Benefits</h6>
                    <?php if ($user['user_type'] === 'pwd'): ?>
                        <p class="mb-1" style="font-weight:600;"><i class="fas fa-check-circle me-1" style="color:var(--success);"></i> 20% PWD Discount</p>
                        <small class="text-muted">Enjoy 20% off on all orders</small>
                    <?php elseif ($user['user_type'] === 'senior'): ?>
                        <p class="mb-1" style="font-weight:600;"><i class="fas fa-check-circle me-1" style="color:var(--success);"></i> 20% Senior Discount</p>
                        <small class="text-muted">Enjoy 20% off on all orders</small>
                    <?php else: ?>
                        <p class="mb-1" style="font-weight:600;"><i class="fas fa-user me-1"></i> Regular Customer</p>
                        <small class="text-muted">Enjoy our premium apparel and service</small>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer/footer.php'; ?>
