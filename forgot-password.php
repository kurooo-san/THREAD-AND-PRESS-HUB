<?php
require 'includes/config.php';

redirectIfLoggedIn();

$pageTitle = 'Forgot Password';
$error = '';
$success = '';

// Run migration if table doesn't exist
$tableCheck = $conn->query("SHOW TABLES LIKE 'password_resets'");
if ($tableCheck->num_rows === 0) {
    $migrationSQL = file_get_contents(__DIR__ . '/database/migrate_password_reset.sql');
    if ($migrationSQL) {
        $conn->multi_query($migrationSQL);
        while ($conn->next_result()) {;}
    }
}

// Same reply whether or not the email is registered, so the form cannot be
// used to find out who has an account.
$genericReply = 'If an account with that email exists, a password reset link has been sent. Please check your inbox.';

// The reset link is only ever shown on the page to someone browsing from this
// machine (XAMPP without SMTP). It used to be shown to anyone whenever the email
// failed to send, which let a stranger reset any account, the admin's included.
// Behind Railway's proxy REMOTE_ADDR is the real visitor, never loopback.
$isLocalRequest = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

// At most this many reset emails per account per hour (stops email bombing).
const RESET_REQUESTS_PER_HOUR = 3;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!verifyCsrfToken()) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        $user = $result->num_rows === 1 ? $result->fetch_assoc() : null;

        $recentRequests = 0;
        if ($user) {
            $countStmt = $conn->prepare("SELECT COUNT(*) AS n FROM password_resets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $countStmt->bind_param("i", $user['id']);
            $countStmt->execute();
            $recentRequests = (int) $countStmt->get_result()->fetch_assoc()['n'];
            $countStmt->close();
        }

        if ($user && $recentRequests < RESET_REQUESTS_PER_HOUR) {
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

            // Build reset link (getBaseUrl honours APP_URL and ignores a forged Host header)
            require_once 'includes/email-helper.php';
            $resetLink = getBaseUrl() . '/reset-password.php?token=' . $token;

            $emailSent = sendPasswordResetEmail($email, $user['fullname'], $resetLink);

            $success = $genericReply;
            if (!$emailSent) {
                error_log('[forgot-password] reset email failed for user #' . $user['id']);
            }
            if (!$emailSent && $isLocalRequest) {
                // Local development only: show the link directly
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
            // Unknown email, or this account hit the hourly cap: same reply either way.
            $success = $genericReply;
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
