<?php
// Load environment variables from .env file
function loadEnv($path = __DIR__ . '/../.env') {
    if (!file_exists($path)) {
        return;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        putenv("{$key}={$value}");
    }
}

loadEnv();

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'threadpresshub');
define('DB_PORT', getenv('DB_PORT') ? (int)getenv('DB_PORT') : 3306);

// Gemini API Configuration
// Get your free API key at: https://makersuite.google.com/app/apikey
// Store your key in the .env file as GEMINI_API_KEY=your_key_here
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');

// reCAPTCHA Configuration
define('RECAPTCHA_SITE_KEY',   getenv('RECAPTCHA_SITE_KEY')   ?: '');
define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY') ?: '');

// Force HTTPS in production
define('FORCE_HTTPS', (getenv('FORCE_HTTPS') ?: 'false') === 'true');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");

// Session configuration - Only start if not already active
if (session_status() === PHP_SESSION_NONE) {
    // Secure session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// Security headers
if (!headers_sent()) {
    // Force HTTPS redirect (production)
    if (FORCE_HTTPS && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        if ($host !== '') {
            header('Location: https://' . $host . $uri, true, 301);
            exit();
        }
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Permissions-Policy. By default the camera/microphone are disabled site-wide.
    // A page that genuinely needs the camera (e.g. the Virtual Try-On page) can
    // opt in by defining ALLOW_CAMERA = true *before* requiring this file, which
    // grants the capability to this same origin only (self).
    if (defined('ALLOW_CAMERA') && ALLOW_CAMERA === true) {
        header('Permissions-Policy: camera=(self), microphone=(self), geolocation=()');
    } else {
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }
    // HSTS — only on HTTPS connections
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

// Helper functions
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirectToLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        header("Location: shop.php");
        exit();
    }
}

function sanitizeInput($data) {
    // NOTE: Do NOT use real_escape_string here — all DB writes use prepared
    // statements (bind_param), and escaping again would double-encode values.
    // This sanitizer only strips whitespace and HTML-encodes for safe output.
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

// CSRF Token helpers
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfTokenField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

function verifyCsrfToken() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function displayMessage($message, $type = 'info') {
    echo "<div class='alert alert-$type' role='alert'>$message</div>";
}

// Discount calculation
function calculateDiscount($userType) {
    $discounts = [
        'pwd' => 0.20,      // 20% discount for PWD
        'senior' => 0.20,   // 20% discount for Senior Citizens
        'regular' => 0.00   // No discount for regular users
    ];
    return $discounts[$userType] ?? 0;
}

function applyDiscount($subtotal, $discountPercent) {
    $discountAmount = $subtotal * $discountPercent;
    return [
        'discount_amount' => $discountAmount,
        'total' => $subtotal - $discountAmount
    ];
}

// Audit logging function
function logAudit($action, $entityType = null, $entityId = null, $details = null) {
    global $conn;
    $tableCheck = $conn->query("SHOW TABLES LIKE 'audit_log'");
    if (!$tableCheck || $tableCheck->num_rows === 0) return;

    $userId = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississs", $userId, $action, $entityType, $entityId, $details, $ip, $ua);
    $stmt->execute();
    $stmt->close();
}

// =============================================================
// Remember Me — persistent login via signed cookie + DB token
// =============================================================
define('REMEMBER_COOKIE_NAME', 'tph_remember');
define('REMEMBER_DURATION_DAYS', 30);

function rememberTokensTableExists() {
    global $conn;
    static $exists = null;
    if ($exists !== null) return $exists;
    $r = $conn->query("SHOW TABLES LIKE 'remember_tokens'");
    $exists = ($r && $r->num_rows > 0);
    return $exists;
}

function setRememberCookie($userId) {
    global $conn;
    if (!rememberTokensTableExists()) return;
    $selector  = bin2hex(random_bytes(16));   // 32 chars
    $validator = bin2hex(random_bytes(32));   // 64 chars (sent to user only)
    $hash      = hash('sha256', $validator);
    $expires   = (new DateTime())->modify('+' . REMEMBER_DURATION_DAYS . ' days');
    $expiresStr = $expires->format('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $selector, $hash, $expiresStr);
    $stmt->execute();
    $stmt->close();

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie(REMEMBER_COOKIE_NAME, $selector . ':' . $validator, [
        'expires'  => $expires->getTimestamp(),
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearRememberCookie() {
    global $conn;
    if (isset($_COOKIE[REMEMBER_COOKIE_NAME])) {
        $parts = explode(':', $_COOKIE[REMEMBER_COOKIE_NAME], 2);
        if (count($parts) === 2 && rememberTokensTableExists()) {
            $selector = $parts[0];
            $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE selector = ?");
            $stmt->bind_param("s", $selector);
            $stmt->execute();
            $stmt->close();
        }
        setcookie(REMEMBER_COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[REMEMBER_COOKIE_NAME]);
    }
}

function tryAutoLoginViaRememberCookie() {
    global $conn;
    if (isset($_SESSION['user_id'])) return;
    if (empty($_COOKIE[REMEMBER_COOKIE_NAME])) return;
    if (!rememberTokensTableExists()) return;

    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE_NAME], 2);
    if (count($parts) !== 2) { clearRememberCookie(); return; }
    [$selector, $validator] = $parts;

    $stmt = $conn->prepare("SELECT rt.user_id, rt.token_hash, rt.expires_at, u.fullname, u.email, u.user_type
                            FROM remember_tokens rt
                            JOIN users u ON u.id = rt.user_id
                            WHERE rt.selector = ? LIMIT 1");
    $stmt->bind_param("s", $selector);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) { clearRememberCookie(); return; }
    if (strtotime($row['expires_at']) < time()) { clearRememberCookie(); return; }

    $expectedHash = hash('sha256', $validator);
    if (!hash_equals($row['token_hash'], $expectedHash)) { clearRememberCookie(); return; }

    // Reject banned users
    $statusCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
    if ($statusCheck && $statusCheck->num_rows > 0) {
        $bs = $conn->prepare("SELECT status FROM users WHERE id = ?");
        $bs->bind_param("i", $row['user_id']);
        $bs->execute();
        $st = $bs->get_result()->fetch_assoc();
        $bs->close();
        if ($st && $st['status'] === 'banned') { clearRememberCookie(); return; }
    }

    $_SESSION['user_id']    = (int)$row['user_id'];
    $_SESSION['user_name']  = $row['fullname'];
    $_SESSION['user_email'] = $row['email'];
    $_SESSION['user_type']  = $row['user_type'];
}

// Auto-login attempt on every request
tryAutoLoginViaRememberCookie();

// =============================================================
// Coupon helpers
// =============================================================
function couponsTableExists() {
    global $conn;
    static $exists = null;
    if ($exists !== null) return $exists;
    $r = $conn->query("SHOW TABLES LIKE 'coupons'");
    $exists = ($r && $r->num_rows > 0);
    return $exists;
}

/**
 * Validate a coupon code against the current cart subtotal.
 * Returns ['ok'=>bool, 'message'=>string, 'coupon'=>row|null, 'discount'=>float]
 */
function validateCoupon($code, $subtotal) {
    global $conn;
    $code = strtoupper(trim($code));
    if ($code === '') return ['ok' => false, 'message' => 'Please enter a coupon code.', 'coupon' => null, 'discount' => 0];
    if (!couponsTableExists()) return ['ok' => false, 'message' => 'Coupons are not enabled.', 'coupon' => null, 'discount' => 0];

    $stmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? LIMIT 1");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $coupon = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$coupon)                                    return ['ok' => false, 'message' => 'Invalid coupon code.',                'coupon' => null, 'discount' => 0];
    if ((int)$coupon['is_active'] !== 1)             return ['ok' => false, 'message' => 'This coupon is not active.',         'coupon' => null, 'discount' => 0];
    if ($coupon['valid_from']  && strtotime($coupon['valid_from'])  > time()) return ['ok' => false, 'message' => 'This coupon is not yet valid.', 'coupon' => null, 'discount' => 0];
    if ($coupon['valid_until'] && strtotime($coupon['valid_until']) < time()) return ['ok' => false, 'message' => 'This coupon has expired.',     'coupon' => null, 'discount' => 0];
    if ($coupon['max_uses'] !== null && (int)$coupon['times_used'] >= (int)$coupon['max_uses']) {
        return ['ok' => false, 'message' => 'This coupon has reached its usage limit.', 'coupon' => null, 'discount' => 0];
    }
    if ($subtotal < (float)$coupon['min_subtotal']) {
        return ['ok' => false, 'message' => 'Minimum subtotal for this coupon is ₱' . number_format($coupon['min_subtotal'], 2) . '.', 'coupon' => null, 'discount' => 0];
    }

    $discount = ($coupon['discount_type'] === 'percent')
        ? round($subtotal * ((float)$coupon['discount_value'] / 100), 2)
        : min((float)$coupon['discount_value'], $subtotal);

    return ['ok' => true, 'message' => 'Coupon applied.', 'coupon' => $coupon, 'discount' => $discount];
}

function incrementCouponUsage($couponId) {
    global $conn;
    if (!couponsTableExists()) return;
    $stmt = $conn->prepare("UPDATE coupons SET times_used = times_used + 1 WHERE id = ?");
    $stmt->bind_param("i", $couponId);
    $stmt->execute();
    $stmt->close();
}

// =============================================================
// Stock helpers
// =============================================================
function productsHasStockColumn() {
    global $conn;
    static $has = null;
    if ($has !== null) return $has;
    $r = $conn->query("SHOW COLUMNS FROM products LIKE 'stock'");
    $has = ($r && $r->num_rows > 0);
    return $has;
}

// True once migrate_ai_design_asset.sql has been run. Everything that touches
// design_asset degrades quietly to "no print file" when the column is absent.
function productsHasDesignColumn() {
    global $conn;
    static $has = null;
    if ($has !== null) return $has;
    $r = $conn->query("SHOW COLUMNS FROM products LIKE 'design_asset'");
    $has = ($r && $r->num_rows > 0);
    return $has;
}

// Swatch colour for a colour name. Lived in shop.php; moved here so
// product.php can render the same swatches without duplicating the map.
// shop.php still declares its own copy behind a function_exists guard.
if (!function_exists('getColorCode')) {
    function getColorCode($colorName) {
        $colors = [
            'Black' => '#000000',
            'White' => '#FFFFFF',
            'Navy' => '#001F3F',
            'Gray' => '#808080',
            'Red' => '#FF4136',
            'Blue' => '#0074D9',
            'Green' => '#2ECC40',
            'Yellow' => '#FFDC00',
            'Pink' => '#FF69B4',
            'Purple' => '#B10DC9',
            'Brown' => '#8B4513',
            'Maroon' => '#800000',
            'Khaki' => '#F0E68C',
            'Beige' => '#F5F5DC',
            'Orange' => '#FF7F00'
        ];
        return $colors[$colorName] ?? '#999999';
    }
}

function getProductStock($productId) {
    global $conn;
    if (!productsHasStockColumn()) return PHP_INT_MAX;
    $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r ? (int)$r['stock'] : 0;
}

function decrementStock($productId, $qty) {
    global $conn;
    if (!productsHasStockColumn()) return true;
    $stmt = $conn->prepare("UPDATE products SET stock = GREATEST(stock - ?, 0) WHERE id = ?");
    $stmt->bind_param("ii", $qty, $productId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Atomically decrement stock only if enough is available.
 * Returns true if the stock was successfully reserved, false otherwise.
 * Use this inside a DB transaction during checkout to prevent overselling.
 */
function tryReserveStock($productId, $qty) {
    global $conn;
    if (!productsHasStockColumn()) return true;
    $qty = (int)$qty;
    $productId = (int)$productId;
    if ($qty <= 0) return false;
    $stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
    $stmt->bind_param("iii", $qty, $productId, $qty);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected === 1;
}

// =============================================================
// reCAPTCHA helpers (Google reCAPTCHA v2 "I'm not a robot")
// =============================================================
function recaptchaEnabled() {
    return RECAPTCHA_SITE_KEY !== '' && RECAPTCHA_SECRET_KEY !== '';
}

function recaptchaScriptTag() {
    if (!recaptchaEnabled()) return '';
    return '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
}

function recaptchaWidget() {
    if (!recaptchaEnabled()) return '';
    return '<div class="g-recaptcha" data-sitekey="' . htmlspecialchars(RECAPTCHA_SITE_KEY, ENT_QUOTES) . '" style="margin: 0.75rem 0;"></div>';
}

function verifyRecaptcha($response, $remoteIp = null) {
    if (!recaptchaEnabled()) return true; // Skip silently if not configured
    if (empty($response)) return false;

    $postData = http_build_query([
        'secret'   => RECAPTCHA_SECRET_KEY,
        'response' => $response,
        'remoteip' => $remoteIp ?? ($_SERVER['REMOTE_ADDR'] ?? ''),
    ]);

    // Prefer cURL, fall back to file_get_contents
    if (function_exists('curl_init')) {
        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $postData,
            'timeout' => 8,
        ]]);
        $body = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
    }

    if (!$body) return false;
    $json = json_decode($body, true);
    return is_array($json) && !empty($json['success']);
}



// Payment channel config + paymentMethodLabel(). Pure definitions, no
// output, so it is safe to load on every page; loading it here means order
// screens, invoices and emails all print the same channel names.
require_once __DIR__ . '/payment-config.php';
