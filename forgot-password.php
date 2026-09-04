<?php
require 'includes/config.php';

redirectIfLoggedIn();

$pageTitle = 'Forgot Password';
$error = '';
$success = '';

// Run migration if table doesn't exist
$tableCheck = $conn->query("SHOW TABLES LIKE 'password_resets'");
if ($tableCheck->num_rows === 0) {
    $migrationSQL = file_get_contents(__DIR__ . '/migrate_password_reset.sql');
    if ($migrationSQL) {
        $conn->multi_query($migrationSQL);
        while ($conn->next_result()) {;}
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Invalidate old tokens for this user
            $invalidate = $conn->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0");
            $invalidate->bind_param("i", $user['id']);
            $invalidate->execute();
            $invalidate->close();

            // Store new token
            $insert = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $insert->bind_param("iss", $user['id'], $token, $expires);
            $insert->execute();
            $insert->close();

            // Build reset link
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $path = dirname($_SERVER['SCRIPT_NAME']);
            $resetLink = $protocol . '://' . $host . $path . '/reset-password.php?token=' . $token;

            // Try to send email
            $emailSent = false;
            require_once 'includes/email-helper.php';
            $emailSent = sendPasswordResetEmail($email, $user['fullname'], $resetLink);

            if ($emailSent) {
                $success = 'A password reset link has been sent to your email address. Please check your inbox.';
            } else {
                // For development: show the link directly
                $success = 'Password reset link generated! <br><br>'
                    . '<div class="alert alert-info" style="border-radius:10px; font-size:0.85rem;">'
                    . '<strong><i class="fas fa-info-circle"></i> Development Mode:</strong> Email sending is not configured. '
                    . 'Use this link to reset your password:<br>'
                    . '<a href="' . htmlspecialchars($resetLink) . '" class="text-break">' . htmlspecialchars($resetLink) . '</a>'
                    . '</div>';
            }

            // Log the action
            logAuditAction($conn, null, 'password_reset_requested', 'user', $user['id'], 'Reset requested for: ' . $email);
        } else {
            // Don't reveal if email exists or not (security)
            $success = 'If an account with that email exists, a password reset link has been sent.';
        }
        $stmt->close();
    }
}

// Audit log helper
function logAuditAction($conn, $userId, $action, $entityType, $entityId, $details) {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'audit_log'");
    if ($tableCheck->num_rows === 0) return;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississs", $userId, $action, $entityType, $entityId, $details, $ip, $ua);
    $stmt->execute();
    $stmt->close();
}
?>

<?php include 'includes/header/header.php'; ?>

<div class="container my-5">
    <div class="form-container">
        <div class="form-brand">
            <span class="brand-logo">TP</span>
        </div>
        <h2 class="form-title">Forgot Password</h2>
        <p class="form-subtitle">Enter your email address and we'll send you a link to reset your password.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="border-radius:12px; border:none; font-size:0.9rem;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" style="border-radius:12px; border:none; font-size:0.9rem;"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (empty($success)): ?>
        <form method="POST">
            <?php echo csrfTokenField(); ?>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" class="form-control" name="email" placeholder="Enter your registered email" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Send Reset Link <i class="fas fa-paper-plane ms-1"></i></button>
        </form>
        <?php endif; ?>

        <div class="form-link mt-3">
            <a href="login.php"><i class="fas fa-arrow-left me-1"></i> Back to Login</a>
        </div>
    </div>
</div>

<?php include 'includes/footer/footer.php'; ?>
