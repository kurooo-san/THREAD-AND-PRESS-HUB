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
    } elseif (!passwordMeetsRules($password)) {
        $error = PASSWORD_RULE_MESSAGE;
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
            <img class="brand-logo logo-light" src="images/logo/logo_sm.png" alt="Thread &amp; Press Hub logo">
            <img class="brand-logo logo-dark" src="images/logo/logo_white_sm.png" alt="Thread &amp; Press Hub logo">
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
                    <input type="password" class="form-control" name="password" id="regPassword" placeholder="Create a password" required aria-describedby="pwRules">
                    <button type="button" class="pw-toggle" aria-label="Show password"><i class="fas fa-eye"></i></button>
                </div>
                <div class="pw-rules" id="pwRules">
                    <p class="pw-rules-title">Password must contain:</p>
                    <ul>
                        <li data-rule="len"><span class="pw-dot"><i class="fas fa-check"></i></span> At least 8 characters</li>
                        <li data-rule="upper"><span class="pw-dot"><i class="fas fa-check"></i></span> One uppercase letter</li>
                        <li data-rule="lower"><span class="pw-dot"><i class="fas fa-check"></i></span> One lowercase letter</li>
                        <li data-rule="num"><span class="pw-dot"><i class="fas fa-check"></i></span> One number</li>
                        <li data-rule="special"><span class="pw-dot"><i class="fas fa-check"></i></span> One special character (!@#$%)</li>
                    </ul>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Confirm Password *</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" name="confirm_password" id="regConfirm" placeholder="Confirm your password" required aria-describedby="pwMatch">
                    <button type="button" class="pw-toggle" aria-label="Show password"><i class="fas fa-eye"></i></button>
                </div>
                <div class="pw-rules pw-match" id="pwMatch" hidden>
                    <ul><li><span class="pw-dot"><i class="fas fa-check"></i></span> <span class="pw-match-text">Passwords match</span></li></ul>
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

<style>
.pw-rules { margin-top: 0.6rem; padding: 0.75rem 0.9rem; border: 1px solid var(--border-light, #e8e8e8); border-radius: 12px; background: rgba(0,0,0,0.015); }
.pw-rules-title { font-size: 0.78rem; font-weight: 600; margin: 0 0 0.4rem; color: #666; }
.pw-rules ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.3rem; }
.pw-rules li { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: #888; transition: color .2s; }
.pw-dot { width: 18px; height: 18px; flex-shrink: 0; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
    background: var(--border-medium, #d0d0d0); color: #fff; font-size: 0.6rem; transition: background .2s, transform .2s; }
.pw-rules li.ok { color: var(--success, #22c55e); font-weight: 500; }
.pw-rules li.ok .pw-dot { background: var(--success, #22c55e); transform: scale(1.1); }
.pw-rules.touched li:not(.ok) .pw-dot { background: var(--danger, #ef4444); }
.pw-rules.touched li:not(.ok) { color: var(--danger, #ef4444); }
html.dark-mode .pw-rules { border-color: rgba(255,255,255,0.12); background: rgba(255,255,255,0.03); }
html.dark-mode .pw-rules-title { color: #bbb; }
html.dark-mode .pw-dot { background: #555; }
.pw-match { padding: 0.5rem 0.9rem; }.pw-match li:not(.ok) .pw-dot i::before { content: "\f00d"; }
</style>

<script>
(function () {
    // Mirrors the server-side check in this file (any non-alphanumeric counts as special).
    var rules = { len: /^.{8,}$/, upper: /[A-Z]/, lower: /[a-z]/, num: /\d/, special: /[^A-Za-z0-9]/ };
    var input = document.getElementById('regPassword');
    var box = document.getElementById('pwRules');
    function check() {
        var v = input.value;
        Object.keys(rules).forEach(function (k) {
            box.querySelector('[data-rule="' + k + '"]').classList.toggle('ok', rules[k].test(v));
        });
    }
    input.addEventListener('input', check);
    input.addEventListener('blur', function () { if (input.value) box.classList.add('touched'); });
    check();

    var confirm = document.getElementById('regConfirm');
    var matchBox = document.getElementById('pwMatch');
    var matchItem = matchBox.querySelector('li');
    function checkMatch() {
        var ok = confirm.value === input.value;
        matchBox.hidden = !confirm.value;
        matchItem.classList.toggle('ok', ok);
        matchBox.querySelector('.pw-match-text').textContent = ok ? 'Passwords match' : 'Passwords do not match';
    }
    confirm.addEventListener('input', function () { matchBox.classList.add('touched'); checkMatch(); });
    input.addEventListener('input', checkMatch);

    document.querySelectorAll('.pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var field = btn.parentNode.querySelector('input');
            var show = field.type === 'password';
            field.type = show ? 'text' : 'password';
            btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });
})();

document.getElementById('userType').addEventListener('change', function() {
    document.getElementById('pwdIdGroup').style.display = this.value === 'pwd' ? 'block' : 'none';
    document.getElementById('seniorIdGroup').style.display = this.value === 'senior' ? 'block' : 'none';
});
</script>

<?php include 'includes/footer/footer.php'; ?>
