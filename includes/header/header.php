<?php
// Admin pages render the sidebar shell instead of the storefront navbar, and
// pull in their own stylesheet. Computed once and reused below.
$isAdminPage = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
$assetBase   = $isAdminPage ? '../' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - Thread & Press Hub' : 'Thread & Press Hub'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../css/style.css' : 'css/style.css'; ?>" rel="stylesheet">
    <!-- Loaded last on purpose: the modern navbar rules must win over style.css. -->
    <?php if (!$isAdminPage): ?>
    <link href="css/navbar-modern.css" rel="stylesheet">
    <?php else: ?>
    <link href="../css/admin-modern.css" rel="stylesheet">
    <?php endif; ?>
</head>
<?php
$bodyClasses = [];
if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
    $bodyClasses[] = 'admin-page';
}
if (!empty($bodyClass)) {
    $bodyClasses[] = $bodyClass;
}

// Initials for the navbar account chip, e.g. "Raymond Lee" -> "RL".
$navUserName = trim((string) ($_SESSION['user_name'] ?? ''));
if ($navUserName === '') {
    $navUserName = 'Account';
}
$navInitials = '';
foreach (preg_split('/\s+/', $navUserName) as $namePart) {
    if ($namePart === '') {
        continue;
    }
    $navInitials .= mb_strtoupper(mb_substr($namePart, 0, 1));
    if (mb_strlen($navInitials) >= 2) {
        break;
    }
}
if ($navInitials === '') {
    $navInitials = 'U';
}
// The chip shows the first name only — no truncation.
$navFirstName = strtok($navUserName, ' ');
if ($navFirstName === false || $navFirstName === '') {
    $navFirstName = $navUserName;
}
?>
<body<?php echo !empty($bodyClasses) ? ' class="' . htmlspecialchars(implode(' ', $bodyClasses)) . '"' : ''; ?>>
    <?php if (!$isAdminPage): // Admin pages use the sidebar shell instead of the storefront bar. ?>
    <!-- Main Navigation -->
    <nav class="navbar navbar-expand-lg cafe-navbar tp-nav sticky-top">
        <div class="container tp-nav-shell">
            <a class="navbar-brand" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../index.php' : 'index.php'; ?>">
                <span class="brand-logo">TP</span>
                <span class="brand-text">
                    <span class="brand-name">Thread &amp; Press</span>
                    <span class="brand-sub">HUB</span>
                </span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if (strpos($_SERVER['PHP_SELF'], '/admin/') === false): ?>
                <ul class="navbar-nav mx-auto align-items-center tp-nav-pill">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shop.php">Shop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shop.php?gender=mens">Men</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shop.php?gender=womens">Women</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shop.php?gender=kids">Kids</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shop.php?category=accessories">Accessories</a>
                    </li>
                </ul>
                <?php endif; ?>
                <ul class="navbar-nav align-items-center tp-nav-actions <?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') === false) ? '' : 'ms-auto'; ?>">
                    <?php if (strpos($_SERVER['PHP_SELF'], '/admin/') === false): ?>
                    <li class="nav-item">
                        <a class="nav-link tp-cta tp-cta-ghost" href="custom-design.php">
                            <i class="fas fa-palette"></i><span>Design Studio</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link tp-cta tp-cta-solid" href="try-on.php">
                            <i class="fas fa-camera"></i><span>Try-On</span>
                        </a>
                    </li>
                    <li class="tp-nav-divider" aria-hidden="true"></li>
                    <li class="nav-item">
                        <a class="nav-link tp-icon-btn" href="cart.php" aria-label="Cart">
                            <svg class="tp-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                                <path d="M3 6h18"/>
                                <path d="M16 10a4 4 0 0 1-8 0"/>
                            </svg>
                            <span class="badge" id="navCartCount" style="display:none;">0</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle tp-user" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <span class="tp-avatar" aria-hidden="true"><?php echo htmlspecialchars($navInitials); ?></span>
                                <span class="tp-user-text">
                                    <span class="tp-user-name"><?php echo htmlspecialchars($navFirstName); ?></span>
                                    <span class="tp-user-sub">My account</span>
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'profile.php' : 'profile.php'; ?>"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../orders.php' : 'orders.php'; ?>"><i class="fas fa-box me-2"></i>Orders</a></li>
                                <li><a class="dropdown-item" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../my-custom-orders.php' : 'my-custom-orders.php'; ?>"><i class="fas fa-shirt me-2"></i>Custom Orders</a></li>
                                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'dashboard.php' : 'admin/dashboard.php'; ?>"><i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../logout.php' : 'logout.php'; ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link btn-signin" href="login.php">Sign In</a>
                        </li>
                        <li class="nav-item ms-1">
                            <a class="btn btn-register" href="register.php">Sign Up</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Search Overlay -->
    <div class="search-overlay" id="searchOverlay">
        <div class="container">
            <div class="search-bar">
                <i class="fas fa-search" style="color: var(--text-light);"></i>
                <input type="text" id="searchInput" placeholder="Search for products..." autocomplete="off">
                <button type="button" onclick="performSearch()">Search</button>
            </div>
        </div>
    </div>

    <script>
        // Active nav link
        document.addEventListener('DOMContentLoaded', function() {
            var path = window.location.pathname.split('/').pop();
            var search = window.location.search;
            if (!path) path = 'index.php';
            document.querySelectorAll('.cafe-navbar .nav-link').forEach(function(link) {
                var href = link.getAttribute('href');
                if (href === path || href === path + search) {
                    link.classList.add('active');
                }
            });

            // Search toggle
            var searchBtn = document.getElementById('navSearchBtn');
            var searchOverlay = document.getElementById('searchOverlay');
            if (searchBtn && searchOverlay) {
                searchBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    searchOverlay.classList.toggle('active');
                    if (searchOverlay.classList.contains('active')) {
                        document.getElementById('searchInput').focus();
                    }
                });
                document.getElementById('searchInput').addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') performSearch();
                });
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') searchOverlay.classList.remove('active');
                });
            }
        });

        function performSearch() {
            var query = document.getElementById('searchInput').value.trim();
            if (query) {
                window.location.href = 'shop.php?search=' + encodeURIComponent(query);
            }
        }
    </script>

    <script>
        /* Navbar scrolled state — adds .is-scrolled past 24px so the bar turns
           translucent + blurred. Separate from animations.js's .navbar-scrolled
           (different class, different threshold); the two do not interact. */
        (function () {
            var nav = document.querySelector('.tp-nav');
            if (!nav) return;

            var queued = false;

            function apply() {
                queued = false;
                nav.classList.toggle('is-scrolled', window.scrollY > 24);
            }

            window.addEventListener('scroll', function () {
                if (queued) return;
                queued = true;
                window.requestAnimationFrame(apply);
            }, { passive: true });

            // Run once: the browser may restore a scrolled position on reload.
            apply();
        })();
    </script>
    <?php endif; // !$isAdminPage ?>

    <main class="container-fluid" style="padding:0;">
