<?php
require 'includes/config.php';
require 'includes/contact-config.php'; // Load contact database config

$pageTitle = 'Contact';
$error = '';
$success = '';

// Get categories for form dropdown
$categories = getContactCategories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $category = trim($_POST['category'] ?? 'general');
    $priority = trim($_POST['priority'] ?? 'normal');
    
    // Validation
    if (empty($name)) {
        $error = '❌ Name is required.';
    } elseif (empty($email)) {
        $error = '❌ Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '❌ Invalid email format.';
    } elseif (empty($subject)) {
        $error = '❌ Subject is required.';
    } elseif (empty($message)) {
        $error = '❌ Message is required.';
    } elseif (strlen($message) < 10) {
        $error = '❌ Message must be at least 10 characters long.';
    } else {
        // Prepare data for insertion
        $data = [
            'name' => $name,
            'email' => $email,
            'phone' => !empty($phone) ? $phone : null,
            'subject' => $subject,
            'message' => $message,
            'category' => $category,
            'priority' => $priority
        ];
        
        // Insert into contact database
        if (insertContactMessage($data)) {
            $success = '✅ Thank you for contacting us! We will get back to you shortly.';
            // Clear form
            $_POST = [];
        } else {
            $error = '❌ Error submitting form. Please try again.';
        }
    }
}
?>

<?php include 'includes/header/header.php'; ?>

<div class="container py-5">
    <div class="row g-5">
        <div class="col-lg-5">
            <span class="hero-badge" style="background: var(--bg-light); color: var(--text-dark); border-color: var(--border-light);">Get in Touch</span>
            <h1 style="font-weight:800; font-size: 2.2rem; margin-top:0.75rem;">Contact Us</h1>
            <p style="color:var(--text-light); line-height:1.7;">Have questions? We'd love to hear from you. Fill out the form and we'll respond as soon as possible.</p>
            
            <div class="mt-4 d-flex flex-column gap-3">
                <div class="d-flex gap-3 align-items-start">
                    <div style="width:42px; height:42px; border-radius:50%; background:var(--bg-light); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas fa-map-marker-alt" style="color:var(--primary);"></i>
                    </div>
                    <div>
                        <h6 style="font-weight:600; margin-bottom:0.15rem;">Address</h6>
                        <p class="text-muted small mb-0">123 Fashion Ave, Cainta, Rizal, Philippines</p>
                    </div>
                </div>
                <div class="d-flex gap-3 align-items-start">
                    <div style="width:42px; height:42px; border-radius:50%; background:var(--bg-light); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas fa-envelope" style="color:var(--primary);"></i>
                    </div>
                    <div>
                        <h6 style="font-weight:600; margin-bottom:0.15rem;">Email</h6>
                        <p class="text-muted small mb-0">support@threadandpress.com</p>
                    </div>
                </div>
                <div class="d-flex gap-3 align-items-start">
                    <div style="width:42px; height:42px; border-radius:50%; background:var(--bg-light); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas fa-phone" style="color:var(--primary);"></i>
                    </div>
                    <div>
                        <h6 style="font-weight:600; margin-bottom:0.15rem;">Phone</h6>
                        <p class="text-muted small mb-0">+63 (2) 8123-4567</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 p-4" style="background:var(--bg-light); border-radius:var(--radius-lg);">

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" style="border-radius:12px; border:none; font-size:0.9rem;"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success" style="border-radius:12px; border:none; font-size:0.9rem;"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" action="contact.php">
                    <?php echo csrfTokenField(); ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:0.82rem; font-weight:600;">Name *</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required placeholder="Your full name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:0.82rem; font-weight:600;">Email *</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required placeholder="your.email@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:0.82rem; font-weight:600;">Phone (Optional)</label>
                            <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="+63 912 345 6789">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:0.82rem; font-weight:600;">Category *</label>
                            <select name="category" class="form-control" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['name']); ?>" 
                                    <?php echo (($_POST['category'] ?? '') === $cat['name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:0.82rem; font-weight:600;">Subject *</label>
                            <input type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required placeholder="What is this about?">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:0.82rem; font-weight:600;">Priority</label>
                            <select name="priority" class="form-control">
                                <option value="low" <?php echo (($_POST['priority'] ?? '') === 'low') ? 'selected' : ''; ?>>Low</option>
                                <option value="normal" <?php echo (($_POST['priority'] ?? 'normal') === 'normal') ? 'selected' : ''; ?>>Normal</option>
                                <option value="high" <?php echo (($_POST['priority'] ?? '') === 'high') ? 'selected' : ''; ?>>High</option>
                                <option value="urgent" <?php echo (($_POST['priority'] ?? '') === 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" style="font-size:0.82rem; font-weight:600;">Message *</label>
                            <textarea name="message" class="form-control" rows="5" required placeholder="Type your message here..." minlength="10"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                            <small class="text-muted" style="font-size:0.78rem;">Minimum 10 characters</small>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-dark btn-lg w-100" style="border-radius:12px; font-weight:600;">
                                Send Message <i class="fas fa-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer/footer.php'; ?>