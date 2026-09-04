<?php

declare(strict_types=1);

/**
 * Save / list virtual try-on snapshots ("saved looks").
 *
 * POST { image: dataURL }  -> saves a generated look to uploads/tryon/{userId}/
 * GET                      -> lists the current user's saved looks
 *
 * Files are stored on disk (per logged-in user) so the live database schema
 * is left untouched. Login-gated; POST is CSRF-checked.
 */

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

const SAVE_DIR_BASE     = __DIR__ . '/../uploads/tryon/';
const SAVE_MAX_BYTES    = 8 * 1024 * 1024;
const SAVE_MAX_PER_USER = 30;

function save_fail(string $message, int $code = 200): void
{
    if ($code !== 200) {
        http_response_code($code);
    }
    echo json_encode(['success' => false, 'error' => $message]);
    exit();
}

if (!isLoggedIn()) {
    save_fail('Please log in.', 401);
}

$userId  = (int) $_SESSION['user_id'];
$userDir = SAVE_DIR_BASE . $userId . '/';

// Public URL prefix (relative to web root) for serving saved looks back.
$publicPrefix = 'uploads/tryon/' . $userId . '/';

// --- GET: list saved looks ----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $looks = [];
    if (is_dir($userDir)) {
        $files = glob($userDir . 'look_*.png') ?: [];
        // Newest first.
        usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        foreach ($files as $file) {
            $looks[] = [
                'url'  => $publicPrefix . basename($file),
                'file' => basename($file),
                'time' => date('M d, Y g:i A', filemtime($file)),
            ];
        }
    }
    echo json_encode(['success' => true, 'looks' => $looks]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    save_fail('Method Not Allowed', 405);
}

// CSRF for writes.
$sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) $sentToken)) {
    save_fail('Invalid security token. Please refresh the page.', 403);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

// --- DELETE action: remove one saved look -------------------------------
if (($input['action'] ?? '') === 'delete') {
    $file = basename((string) ($input['file'] ?? ''));
    // Only allow our own generated filenames.
    if ($file === '' || !preg_match('/^look_[A-Za-z0-9_]+\.png$/', $file)) {
        save_fail('Invalid file.');
    }
    $path = $userDir . $file;
    if (is_file($path)) {
        @unlink($path);
    }
    echo json_encode(['success' => true]);
    exit();
}

$image = (string) ($input['image'] ?? '');

if (!preg_match('#^data:image/(png|jpeg|webp);base64,(.*)$#s', $image, $m)) {
    save_fail('Invalid image data.');
}
$bytes = base64_decode($m[2], true);
if ($bytes === false || $bytes === '' || strlen($bytes) > SAVE_MAX_BYTES) {
    save_fail('Image could not be saved (empty or too large).');
}
if (@getimagesizefromstring($bytes) === false) {
    save_fail('Saved data is not a valid image.');
}

// Ensure the per-user directory exists.
if (!is_dir($userDir) && !@mkdir($userDir, 0775, true) && !is_dir($userDir)) {
    error_log('Try-on save: could not create directory ' . $userDir);
    save_fail('Could not save your look. Please try again.');
}

// Enforce a per-user cap by deleting the oldest files beyond the limit.
$existing = glob($userDir . 'look_*.png') ?: [];
if (count($existing) >= SAVE_MAX_PER_USER) {
    usort($existing, static fn($a, $b) => filemtime($a) <=> filemtime($b));
    while (count($existing) >= SAVE_MAX_PER_USER) {
        @unlink(array_shift($existing));
    }
}

$filename = 'look_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
if (@file_put_contents($userDir . $filename, $bytes) === false) {
    save_fail('Could not write the image to disk.');
}

echo json_encode([
    'success' => true,
    'url'     => $publicPrefix . $filename,
]);
