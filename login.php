<?php
require 'includes/config.php';

redirectIfLoggedIn();

$pageTitle = 'Login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Email and password are required!';
        } else {
            // Rate limiting: check login attempts
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $rateLimited = false;
            $attemptsTable = $conn->query("SHOW TABLES LIKE 'login_attempts'");
            if ($attemptsTable && $attemptsTable->num_rows > 0) {
                // Clean old attempts (older than 15 minutes)
                $conn->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
                // Check recent attempts
                $checkAttempts = $conn->prepare("SELECT COUNT(*) as cnt FROM login_attempts WHERE (email = ? OR ip_address = ?) AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
                $checkAttempts->bind_param("ss", $email, $ip);
                $checkAttempts->execute();
                $attemptCount = $checkAttempts->get_result()->fetch_assoc()['cnt'];
                $checkAttempts->close();
                if ($attemptCount >= 5) {
                    $rateLimited = true;
                    $error = 'Too many login attempts. Please try again in 15 minutes.';
                }
            }

            if (!$rateLimited) {
                // Detect optional `status` column (banned users)
                $hasStatusCol = false;
                $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
                if ($colCheck && $colCheck->num_rows > 0) { $hasStatusCol = true; }
                $selectCols = $hasStatusCol
                    ? "id, fullname, email, password, user_type, status"
                    : "id, fullname, email, password, user_type";
                $stmt = $conn->prepare("SELECT $selectCols FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if ($hasStatusCol && isset($user['status']) && $user['status'] === 'banned') {
                        $error = 'This account has been suspended. Please contact support.';
                        $stmt->close();
                        // Skip rest of login logic
                        goto end_login_flow;
                    }
                    if (verifyPassword($password, $user['password'])) {
                        // Clear login attempts on success
                        if ($attemptsTable && $attemptsTable->num_rows > 0) {
                            $clearAttempts = $conn->prepare("DELETE FROM login_attempts WHERE email = ? OR ip_address = ?");
                            $clearAttempts->bind_param("ss", $email, $ip);
                            $clearAttempts->execute();
                            $clearAttempts->close();
                        }

                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['fullname'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_type'] = $user['user_type'];

                        // Remember Me
                        if (!empty($_POST['remember_me'])) {
                            setRememberCookie((int)$user['id']);
                        }

                        // Audit log
                        logAudit('user_login', 'user', $user['id'], 'Login successful');
                        
                        // Redirect based on user type
                        if ($user['user_type'] === 'admin') {
                            header("Location: admin/dashboard.php");
                        } else {
                            header("Location: index.php");
                        }
                        exit();
                    } else {
                        $error = 'Invalid email or password!';
                    }
                } else {
                    $error = 'Invalid email or password!';
                }
                $stmt->close();

                // Record failed attempt
                if (!empty($error) && $attemptsTable && $attemptsTable->num_rows > 0) {
                    $recordAttempt = $conn->prepare("INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)");
                    $recordAttempt->bind_param("ss", $email, $ip);
                    $recordAttempt->execute();
                    $recordAttempt->close();
                }
                end_login_flow:;
            }
        }
    }
}
?>

<?php include 'includes/header/header.php'; ?>

<div class="container my-5">
    <div class="form-container">
        <div class="form-brand">
            <span class="brand-logo">TP</span>
        </div>
        <h2 class="form-title">Welcome Back</h2>
        <p class="form-subtitle">Sign in to your Thread &amp; Press Hub account</p>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="border-radius:12px; border:none; font-size:0.9rem;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <?php echo csrfTokenField(); ?>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" class="form-control" name="email" placeholder="Enter your email" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" name="password" placeholder="Enter your password" required>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check" style="margin:0;">
                    <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me" value="1">
                    <label class="form-check-label small" for="rememberMe">Remember me for 30 days</label>
                </div>
                <a href="forgot-password.php" class="small text-decoration-none" style="color:var(--primary);">Forgot password?</a>
            </div>

            <button type="submit" class="btn btn-primary w-100">Sign In <i class="fas fa-arrow-right ms-1"></i></button>
        </form>

        

        <div class="form-link">
            Don't have an account? <a href="register.php">Sign up</a>
        </div>
    </div>
</div>

<?php include 'includes/footer/footer.php'; ?>
