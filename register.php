<?php
require 'includes/config.php';

redirectIfLoggedIn();

$pageTitle = 'Register';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitizeInput($_POST['fullname'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type = sanitizeInput($_POST['user_type'] ?? 'regular');
    // Never trust the posted role: the column enum also allows 'admin'.
    if (!in_array($user_type, ['regular', 'pwd', 'senior'], true)) {
        $user_type = 'regular';
    }
    $pwd_id = sanitizeInput($_POST['pwd_id'] ?? '');
    $senior_id = sanitizeInput($_POST['senior_id'] ?? '');
    $street_address = sanitizeInput($_POST['street_address'] ?? '');
    $barangay = sanitizeInput($_POST['barangay'] ?? '');
    $city = sanitizeInput($_POST['city'] ?? '');
    $province = sanitizeInput($_POST['province'] ?? '');
    $zipcode = sanitizeInput($_POST['zipcode'] ?? '');

    // CSRF check
    if (!verifyCsrfToken()) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All required fields must be filled!';
    } elseif (empty($street_address) || empty($barangay) || empty($city) || empty($province) || empty($zipcode)) {
        $error = 'All delivery address fields are required!';
    } elseif (!preg_match('/^[0-9]{4}$/', $zipcode)) {
        // Same rule as saved addresses (addressValidate), so the signup address
        // can always become the customer's first saved address.
        $error = 'Zip code should be 4 digits.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match!';
    } elseif (strlen($password) < 8
        || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[a-z]/', $password)
        || !preg_match('/\d/',   $password)
        || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Password must be at least 8 characters and contain uppercase, lowercase, number, and a special character.';
    } else {
        // Check if email already exists
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        
        if ($check_email->get_result()->num_rows > 0) {
            $error = 'Email already registered!';
        } else {
            $hashed_password = hashPassword($password);
            
            $stmt = $conn->prepare("INSERT INTO users (fullname, email, phone, password, user_type, pwd_id, senior_id, street_address, barangay, city, province, zipcode, created_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssssssssssss", $fullname, $email, $phone, $hashed_password, $user_type, $pwd_id, $senior_id, $street_address, $barangay, $city, $province, $zipcode);
            
            if ($stmt->execute()) {
                // Send welcome email
                require_once 'includes/email-helper.php';
                sendWelcomeEmail($email, $fullname);
                
                $success = 'Registration successful! You can now <a href="login.php">login</a>.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
            $stmt->close();
        }
        $check_email->close();
    }
}
?>

<?php include 'includes/header/header.php'; ?>

<div class="container my-5">
    <div class="form-container">
        <div class="form-brand">
            <span class="brand-logo">TP</span>
        </div>
        <h2 class="form-title">Create Account</h2>
        <p class="form-subtitle">Join Thread &amp; Press Hub and start shopping</p>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="border-radius:12px; border:none; font-size:0.9rem;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" style="border-radius:12px; border:none; font-size:0.9rem;"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" class="needs-validation">
            <?php echo csrfTokenField(); ?>
            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" class="form-control" name="fullname" placeholder="Enter your full name" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Email Address *</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" class="form-control" name="email" placeholder="Enter your email" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Phone Number</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-phone"></i>
                    <input type="tel" class="form-control" name="phone" placeholder="Enter your phone number">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Account Type *</label>
                <select class="form-control" name="user_type" id="userType" required style="border-radius:12px; padding: 0.75rem 1rem;">
                    <option value="regular">Regular Customer</option>
                    <option value="pwd">Person with Disability (PWD)</option>
                    <option value="senior">Senior Citizen</option>
                </select>
            </div>

            <div class="form-group" id="pwdIdGroup" style="display: none;">
                <label class="form-label">PWD ID Number</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-id-card"></i>
                    <input type="text" class="form-control" name="pwd_id" placeholder="Enter your PWD ID">
                </div>
            </div>

            <div class="form-group" id="seniorIdGroup" style="display: none;">
                <label class="form-label">Senior Citizen ID Number</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-id-card"></i>
                    <input type="text" class="form-control" name="senior_id" placeholder="Enter your Senior ID">
                </div>
            </div>

            <!-- Delivery Address Section -->
            <div style="margin: 1.5rem 0 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                <h6 style="font-weight: 700; font-size: 0.95rem; margin-bottom: 0.25rem;"><i class="fas fa-map-marker-alt me-1" style="color:var(--primary, #2d6a4f);"></i> Delivery Address</h6>
                <p style="font-size: 0.78rem; color: #888; margin-bottom: 1rem;">This address will be used for your deliveries. You can update it later in your profile.</p>
            </div>

            <div class="form-group">
                <label class="form-label">Street Address *</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-road"></i>
                    <input type="text" class="form-control" name="street_address" placeholder="House/Unit No., Street Name" required value="<?php echo htmlspecialchars($_POST['street_address'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Barangay *</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-map-pin"></i>
                    <input type="text" class="form-control" name="barangay" placeholder="Enter your barangay" required value="<?php echo htmlspecialchars($_POST['barangay'] ?? ''); ?>">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div class="form-group">
                    <label class="form-label">City *</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-city"></i>
                        <input type="text" class="form-control" name="city" placeholder="City" required value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Province *</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-map"></i>
                        <input type="text" class="form-control" name="province" placeholder="Province" required value="<?php echo htmlspecialchars($_POST['province'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Zip Code *</label>
                <div class="input-icon-wrapper" style="max-width: 200px;">
                    <i class="fas fa-hashtag"></i>
                    <input type="text" class="form-control" name="zipcode" placeholder="Zip Code" required maxlength="4" inputmode="numeric" pattern="[0-9]{4}" title="Enter your 4-digit zip code" value="<?php echo htmlspecialchars($_POST['zipcode'] ?? ''); ?>">
                </div>
            </div>

            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #eee;">
                <h6 style="font-weight: 700; font-size: 0.95rem; margin-bottom: 1rem;"><i class="fas fa-lock me-1" style="color:var(--primary, #2d6a4f);"></i> Security</h6>
            </div>

            <div class="form-group">
                <label class="form-label">Password *</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" name="password" placeholder="Create a password" required>
                </div>
                <small class="text-muted" style="font-size:0.78rem;">Min. 8 chars w/ uppercase, lowercase, number &amp; special character</small>
            </div>

            <div class="form-group">
                <label class="form-label">Confirm Password *</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" name="confirm_password" placeholder="Confirm your password" required>
                </div>
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="agreeTerms" required>
                <label class="form-check-label small" for="agreeTerms">
                    I agree to the <a href="about.php" class="text-decoration-none" style="color:var(--primary);">Terms of Service</a> and <a href="privacy-policy.php" class="text-decoration-none" style="color:var(--primary);">Privacy Policy</a>
                </label>
            </div>

            <button type="submit" class="btn btn-primary w-100">Create Account <i class="fas fa-arrow-right ms-1"></i></button>
        </form>

        <div class="form-divider"><span>or</span></div>

       

        <div class="form-link">
            Already have an account? <a href="login.php">Sign in</a>
        </div>
    </div>
</div>

<script>
document.getElementById('userType').addEventListener('change', function() {
    document.getElementById('pwdIdGroup').style.display = this.value === 'pwd' ? 'block' : 'none';
    document.getElementById('seniorIdGroup').style.display = this.value === 'senior' ? 'block' : 'none';
});
</script>

<?php include 'includes/footer/footer.php'; ?>
