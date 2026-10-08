<?php
// Shown on the pages guests may browse (shop.php, product.php). Signed-in
// visitors never see it. Sign in brings them back to this same page.
if (isLoggedIn()) {
    return;
}
$guestBack = safeRedirectTarget($_SERVER['REQUEST_URI'] ?? '');
$guestLogin = 'login.php' . ($guestBack ? '?redirect=' . urlencode($guestBack) : '');
?>
<div class="alert alert-info d-flex align-items-center flex-wrap gap-2 mb-3" role="note" style="border-radius:12px; font-size:0.9rem;">
    <i class="fas fa-user-clock"></i>
    <span class="me-auto">You're browsing as a guest. Sign in to save your cart, check out, and design your own apparel.</span>
    <a href="<?php echo htmlspecialchars($guestLogin, ENT_QUOTES); ?>" class="btn btn-sm btn-dark" style="border-radius:8px;">Sign in</a>
    <a href="register.php" class="btn btn-sm btn-outline-dark" style="border-radius:8px;">Create account</a>
</div>
