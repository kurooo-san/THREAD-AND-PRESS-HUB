<?php
require 'includes/config.php';

redirectIfLoggedIn();

$pageTitle = 'Reset Password';
$error = '';
$success = '';
$validToken = false;
$token = $_GET['token'] ?? $_POST['token'] ?? '';

// Run migration if table doesn't exist
$tableCheck = $conn->query("SHOW TABLES LIKE 'password_resets'");
if ($tableCheck->num_rows === 0) {
    $migrationSQL = file_get_contents(__DIR__ . '/migrate_password_reset.sql');
    if ($migrationSQL) {
        $conn->multi_query($migrationSQL);
        while ($conn->next_result()) {;}
    }
}

// Validate token
if (!empty($token)) {
    $stmt = $conn->prepare("SELECT pr.id, pr.user_id, pr.expires_at, u.email, u.fullname 
                            FROM password_resets pr 
                            JOIN users u ON pr.user_id = u.id 
                            WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $resetData = $result->fetch_assoc();
        $validToken = true;
    } else {
        $error = 'This password reset link is invalid or has expired. Please request a new one.';
    }
    $stmt->close();
} else {
    $error = 'No reset token provided. Please request a password reset from the login page.';
}

// Handle password reset form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!verifyCsrfToken()) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (empty($password) || empty($confirmPassword)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $hashedPassword = hashPassword($password);

        // Update user password
        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->bind_param("si", $hashedPassword, $resetData['user_id']);

        if ($updateStmt->execute()) {
            // Mark token as used
            $markUsed = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $markUsed->bind_param("s", $token);
            $markUsed->execute();
            $markUsed->close();

            $success = 'Your password has been reset successfully! You can now <a href="login.php">login</a> with your new password.';
            $validToken = false; // Hide the form
        } else {
            $error = 'Failed to reset password. Please try again.';
        }
        $updateStmt->close();
    }
}
?>

<?php include 'includes/header/header.php'; ?>

<div class="container my-5">
    <div class="form-container">
        <div class="form-brand">
            <span class="brand-logo">TP</span>
        </div>
        <h2 class="form-title">Reset Password</h2>
        <p class="form-subtitle">Enter your new password below.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="border-radius:12px; border:none; font-size:0.9rem;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" style="border-radius:12px; border:none; font-size:0.9rem;"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($validToken): ?>
        <div class="alert alert-info" style="border-radius:12px; border:none; font-size:0.85rem;">
            <i class="fas fa-user me-1"></i> Resetting password for: <strong><?php echo htmlspecialchars($resetData['email']); ?></strong>
        </div>

        <form method="POST">
            <?php echo csrfTokenField(); ?>
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <div class="form-group">
                <label class="form-label">New Password</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" name="password" placeholder="Enter new password (min 6 chars)" required minlength="6">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" name="confirm_password" placeholder="Confirm new password" required minlength="6">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Reset Password <i class="fas fa-check ms-1"></i></button>
        </form>
        <?php endif; ?>

        <?php if (!$validToken && empty($success)): ?>
        <div class="text-center mt-3">
            <a href="forgot-password.php" class="btn btn-outline-primary">Request New Reset Link</a>
        </div>
        <?php endif; ?>

        <div class="form-link mt-3">
            <a href="login.php"><i class="fas fa-arrow-left me-1"></i> Back to Login</a>
        </div>
    </div>
</div>

<?php include 'includes/footer/footer.php'; ?>
