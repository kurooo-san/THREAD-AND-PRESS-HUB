<?php
require 'includes/config.php';
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

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitizeInput($_POST['fullname'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $street_address = sanitizeInput($_POST['street_address'] ?? '');
    $barangay = sanitizeInput($_POST['barangay'] ?? '');
    $city = sanitizeInput($_POST['city'] ?? '');
    $province = sanitizeInput($_POST['province'] ?? '');
    $zipcode = sanitizeInput($_POST['zipcode'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    if (!verifyCsrfToken()) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (empty($fullname)) {
        $error = 'Full name cannot be empty!';
    } else {
        $update_stmt = $conn->prepare("UPDATE users SET fullname = ?, phone = ?, street_address = ?, barangay = ?, city = ?, province = ?, zipcode = ? WHERE id = ?");
        $update_stmt->bind_param("sssssssi", $fullname, $phone, $street_address, $barangay, $city, $province, $zipcode, $_SESSION['user_id']);
        
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

                        <h6 style="font-weight:700; margin-bottom:0.5rem;"><i class="fas fa-map-marker-alt me-1" style="color:var(--accent-green, #2d6a4f);"></i> Delivery Address</h6>
                        <p style="font-size:0.78rem; color:#888; margin-bottom:1rem;">This address will be pre-filled during checkout for delivery orders.</p>

                        <div class="form-group mb-3">
                            <label class="form-label" style="font-size:0.82rem; font-weight:600;">Street Address</label>
                            <input type="text" class="form-control" name="street_address" placeholder="House/Unit No., Street Name" value="<?php echo htmlspecialchars($user['street_address'] ?? ''); ?>">
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label" style="font-size:0.82rem; font-weight:600;">Barangay</label>
                            <input type="text" class="form-control" name="barangay" placeholder="Barangay" value="<?php echo htmlspecialchars($user['barangay'] ?? ''); ?>">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" style="font-size:0.82rem; font-weight:600;">City</label>
                                <input type="text" class="form-control" name="city" placeholder="City" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" style="font-size:0.82rem; font-weight:600;">Province</label>
                                <input type="text" class="form-control" name="province" placeholder="Province" value="<?php echo htmlspecialchars($user['province'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label" style="font-size:0.82rem; font-weight:600;">Zip Code</label>
                            <input type="text" class="form-control" name="zipcode" placeholder="Zip Code" maxlength="10" style="max-width:200px;" value="<?php echo htmlspecialchars($user['zipcode'] ?? ''); ?>">
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
