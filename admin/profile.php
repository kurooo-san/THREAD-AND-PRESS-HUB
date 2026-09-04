<?php
require '../includes/config.php';
redirectToLogin();

$pageTitle = 'Admin Profile';
$admin_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch admin details
$stmt = $conn->prepare("SELECT id, fullname, email, user_type FROM users WHERE id = ? AND user_type = 'admin'");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

if (!$admin) {
    header("Location: dashboard.php");
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitizeInput($_POST['fullname'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (empty($fullname) || empty($email)) {
        $error = 'Name and email are required!';
    } else {
        // Check if email is already used by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $admin_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'Email is already in use!';
        }
        $stmt->close();

        // If trying to change password
        if (!empty($newPassword) || !empty($confirmPassword)) {
            if (empty($currentPassword)) {
                $error = 'Current password is required to set a new password!';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New passwords do not match!';
            } elseif (strlen($newPassword) < 6) {
                $error = 'New password must be at least 6 characters!';
            } else {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->bind_param("i", $admin_id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!verifyPassword($currentPassword, $row['password'])) {
                    $error = 'Current password is incorrect!';
                } else {
                    // Update profile with new password
                    $newPasswordHash = hashPassword($newPassword);
                    $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, password = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $fullname, $email, $newPasswordHash, $admin_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['user_name'] = $fullname;
                        $_SESSION['user_email'] = $email;
                        $message = 'Profile updated successfully!';
                        $admin['fullname'] = $fullname;
                        $admin['email'] = $email;
                    } else {
                        $error = 'Failed to update profile. Please try again.';
                    }
                    $stmt->close();
                }
            }
        } else {
            // Update profile without password change
            $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $fullname, $email, $admin_id);
            
            if ($stmt->execute()) {
                $_SESSION['user_name'] = $fullname;
                $_SESSION['user_email'] = $email;
                $message = 'Profile updated successfully!';
                $admin['fullname'] = $fullname;
                $admin['email'] = $email;
            } else {
                $error = 'Failed to update profile. Please try again.';
            }
            $stmt->close();
        }
    }
}
?>

<?php include '../includes/header/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-user-edit"></i> Admin Profile</h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <!-- Full Name -->
                        <div class="mb-3">
                            <label for="fullname" class="form-label"><i class="fas fa-user"></i> Full Name</label>
                            <input type="text" class="form-control" id="fullname" name="fullname" value="<?php echo htmlspecialchars($admin['fullname']); ?>" required>
                            <small class="text-muted">Your full name</small>
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label"><i class="fas fa-envelope"></i> Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                            <small class="text-muted">Your email address (must be unique)</small>
                        </div>

                        <!-- User Type (Read-only) -->
                        <div class="mb-3">
                            <label for="usertype" class="form-label"><i class="fas fa-shield-alt"></i> User Type</label>
                            <input type="text" class="form-control" id="usertype" value="Administrator" disabled>
                            <small class="text-muted">Your account type</small>
                        </div>

                        <hr>

                        <!-- Change Password Section -->
                        <h6 class="mb-3" style="font-weight: 700;">Change Password (Optional)</h6>

                        <!-- Current Password -->
                        <div class="mb-3">
                            <label for="current_password" class="form-label"><i class="fas fa-lock"></i> Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Leave blank if not changing password">
                            <small class="text-muted">Required to set a new password</small>
                        </div>

                        <!-- New Password -->
                        <div class="mb-3">
                            <label for="new_password" class="form-label"><i class="fas fa-lock"></i> New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Minimum 6 characters">
                            <small class="text-muted">Leave blank to keep current password</small>
                        </div>

                        <!-- Confirm Password -->
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label"><i class="fas fa-lock"></i> Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                        </div>

                        <!-- Buttons -->
                        <div class="d-grid gap-2 d-sm-flex">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Account Information Card -->
            <div class="card border-0 shadow mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Account Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Admin ID:</strong> #<?php echo $admin['id']; ?></p>
                    <p><strong>Account Type:</strong> <span class="badge bg-danger">Administrator</span></p>
                    <p class="text-muted small"><i class="fas fa-shield-alt"></i> You have full access to all admin features and user management.</p>
                </div>
            </div>
        </div>
    </div>
</div>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->
<script src="../js/admin-sidebar.js"></script>

<?php include '../includes/footer/footer.php'; ?>
