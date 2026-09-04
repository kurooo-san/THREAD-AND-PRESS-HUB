<?php

declare(strict_types=1);

/**
 * AI Virtual Try-On endpoint (LiveLook — Option A).
 *
 * Receives a webcam frame (base64) + a product id, loads the product's
 * garment photo from disk, and asks Google's Gemini image model to render
 * the person wearing that exact garment. Returns the generated image as a
 * data URL.
 *
 * Security: login-gated, POST-only, CSRF-checked. The API key never leaves
 * the server — all AI calls are proxied here.
 */

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// --- Configuration ------------------------------------------------------
// Gemini image-generation/editing model ("Nano Banana"). Swappable here if
// Google renames or you move to another image-capable model.
const TRYON_MODEL          = 'gemini-2.5-flash-image';
const TRYON_TIMEOUT_SECS   = 60;
const TRYON_MAX_IMAGE_BYTES = 6 * 1024 * 1024; // ~6MB decoded person image cap
const PRODUCT_IMAGE_DIR    = __DIR__ . '/../images/products/';

/** Emit a JSON error and stop. */
function tryon_fail(string $message, int $httpCode = 200): void
{
    if ($httpCode !== 200) {
        http_response_code($httpCode);
    }
    echo json_encode(['success' => false, 'error' => $message]);
    exit();
}

// --- Guards -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tryon_fail('Method Not Allowed', 405);
}

if (!isLoggedIn()) {
    tryon_fail('Please log in to use Virtual Try-On.', 401);
}

$apiKey = defined('GEMINI_API_KEY') && GEMINI_API_KEY ? GEMINI_API_KEY : (getenv('GEMINI_API_KEY') ?: '');
if ($apiKey === '') {
    tryon_fail('Virtual Try-On is not configured. Please set GEMINI_API_KEY in your .env file.');
}

// CSRF — token is sent in a header by the page's JS.
$sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) $sentToken)) {
    tryon_fail('Invalid or missing security token. Please refresh the page.', 403);
}

// --- Input --------------------------------------------------------------
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    tryon_fail('Malformed request.');
}

$productId   = filter_var($input['productId'] ?? null, FILTER_VALIDATE_INT);
$personImage = (string) ($input['personImage'] ?? '');

if (!$productId) {
    tryon_fail('A valid product must be selected.');
}

// Accept either a raw base64 string or a data URL; normalise to raw bytes.
$personBytes = decode_data_image($personImage, $personMime);
if ($personBytes === null) {
    tryon_fail('Could not read the camera frame. Please try again.');
}
if (strlen($personBytes) > TRYON_MAX_IMAGE_BYTES) {
    tryon_fail('Camera frame is too large. Please try again.');
}

// --- Load the garment image from the catalog (prepared statement) -------
$stmt = $conn->prepare("SELECT name, image FROM products WHERE id = ? AND status = 'active' LIMIT 1");
if (!$stmt) {
    tryon_fail('Database error. Please try again later.');
}
$stmt->bind_param('i', $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    tryon_fail('That product is no longer available.');
}

// Resolve the garment file safely inside the product image directory.
$garmentPath = resolve_product_image((string) $product['image']);
if ($garmentPath === null) {
    tryon_fail('The garment image for this product is missing.');
}

$garmentBytes = @file_get_contents($garmentPath);
if ($garmentBytes === false || $garmentBytes === '') {
    tryon_fail('Could not load the garment image.');
}
$garmentMime = mime_from_path($garmentPath);

// --- Build the Gemini request -------------------------------------------
$prompt = 'You are a virtual try-on engine. The FIRST image is a real photo of a '
    . 'person (the customer). The SECOND image is a garment ("' . $product['name'] . '"). '
    . 'Generate a single photorealistic image of the SAME person wearing this exact '
    . 'garment. Preserve the person\'s face, hair, skin tone, body pose, lighting and '
    . 'background exactly. Fit the garment naturally to their torso with realistic '
    . 'folds, shadows and proportions. Keep the garment\'s colour, pattern, print and '
    . 'design faithful to the second image. Output only the final image.';

$requestData = [
    'contents' => [[
        'role'  => 'user',
        'parts' => [
            ['text' => $prompt],
            ['inlineData' => ['mimeType' => $personMime,  'data' => base64_encode($personBytes)]],
            ['inlineData' => ['mimeType' => $garmentMime, 'data' => base64_encode($garmentBytes)]],
        ],
    ]],
    'generationConfig' => [
        'temperature' => 0.4,
    ],
];

$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/'
    . TRYON_MODEL . ':generateContent?key=' . urlencode($apiKey);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($requestData),
    CURLOPT_TIMEOUT        => TRYON_TIMEOUT_SECS,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// --- Handle transport / API errors --------------------------------------
if ($curlError) {
    tryon_fail('Connection error: unable to reach the AI service. Please try again.');
}

if ($httpCode === 429) {
    tryon_fail('The AI service is busy right now (rate limit). Please wait a moment and try again.');
}

if ($httpCode !== 200) {
    $errorData = json_decode((string) $response, true);
    $msg = $errorData['error']['message'] ?? ('The AI service returned an error (HTTP ' . $httpCode . ').');
    error_log('Try-on API error (' . $httpCode . '): ' . $msg);
    tryon_fail('Try-on failed: ' . $msg);
}

// --- Extract the generated image ----------------------------------------
$apiResponse = json_decode((string) $response, true);
$parts = $apiResponse['candidates'][0]['content']['parts'] ?? [];

$resultData = null;
$resultMime = 'image/png';
foreach ($parts as $part) {
    if (isset($part['inlineData']['data'])) {
        $resultData = $part['inlineData']['data'];
        $resultMime = $part['inlineData']['mimeType'] ?? 'image/png';
        break;
    }
}

if ($resultData === null) {
    // The model sometimes refuses (safety) and returns only text.
    $finish = $apiResponse['candidates'][0]['finishReason'] ?? '';
    if ($finish === 'SAFETY' || $finish === 'PROHIBITED_CONTENT') {
        tryon_fail('The AI could not process this frame. Try a clearer, well-lit photo facing the camera.');
    }
    tryon_fail('The AI did not return an image. Please try again with a different pose or lighting.');
}

echo json_encode([
    'success'     => true,
    'resultImage' => 'data:' . $resultMime . ';base64,' . $resultData,
    'productName' => $product['name'],
]);

// =======================================================================
// Helpers
// =======================================================================

/**
 * Decode a data URL or bare base64 string into raw image bytes.
 * Sets $mime to the detected/declared MIME type. Returns null on failure.
 */
function decode_data_image(string $value, ?string &$mime): ?string
{
    $mime = 'image/jpeg';
    if ($value === '') {
        return null;
    }

    if (preg_match('#^data:(image/[a-zA-Z0-9.+-]+);base64,(.*)$#s', $value, $m)) {
        $mime = strtolower($m[1]);
        $value = $m[2];
    }

    $bytes = base64_decode($value, true);
    if ($bytes === false || $bytes === '') {
        return null;
    }

    // Verify it really is an image and normalise the MIME type.
    $info = @getimagesizefromstring($bytes);
    if ($info === false || empty($info['mime'])) {
        return null;
    }
    $mime = $info['mime'];

    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed, true)) {
        return null;
    }

    return $bytes;
}

/**
 * Resolve a product image filename to an absolute path, guarding against
 * path traversal. Returns null if the file is outside the product dir or
 * does not exist.
 */
function resolve_product_image(string $filename): ?string
{
    if ($filename === '') {
        return null;
    }
    // Only the basename is trusted — strip any directory components.
    $safe = basename($filename);
    $full = PRODUCT_IMAGE_DIR . $safe;

    $real    = realpath($full);
    $baseReal = realpath(PRODUCT_IMAGE_DIR);
    if ($real === false || $baseReal === false) {
        return null;
    }
    if (strncmp($real, $baseReal, strlen($baseReal)) !== 0) {
        return null;
    }
    return $real;
}

/** Best-effort MIME detection for a local image file. */
function mime_from_path(string $path): string
{
    $info = @getimagesize($path);
    if ($info !== false && !empty($info['mime'])) {
        return $info['mime'];
    }
    return 'image/jpeg';
}
