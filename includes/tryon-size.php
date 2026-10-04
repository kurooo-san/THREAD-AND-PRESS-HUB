<?php

declare(strict_types=1);

/**
 * AI Size Recommender endpoint (Try-On "Find my size").
 *
 * Receives height, weight and preferred fit for one product and asks Gemini
 * which of THAT product's sizes fits best, using the store's size guide.
 * Text only — no photo is sent.
 *
 * Security: login-gated, POST-only, CSRF-checked. The product and its sizes
 * are loaded server-side, and the answer must be one of those sizes.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/size-estimate.php';   // estimateSize(): used whenever the AI can't answer

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

const SIZE_MODEL        = 'gemini-2.5-flash';
const SIZE_TIMEOUT_SECS = 30;

// Same guide the chatbot quotes (includes/gemini_api.php, SIZE GUIDE).
const SIZE_GUIDE = [
    't-shirts' => "XS: chest 32-34 in, length 26 in (kids/petite); S: chest 34-36 in, length 27 in; M: chest 38-40 in, length 28 in; L: chest 42-44 in, length 29 in; XL: chest 46-48 in, length 30 in; XXL: chest 50-52 in, length 31 in",
    'hoodies'  => "XS: chest 32-34 in, length 26 in (kids/petite); S: chest 34-36 in, length 27 in; M: chest 38-40 in, length 28 in; L: chest 42-44 in, length 29 in; XL: chest 46-48 in, length 30 in; XXL: chest 50-52 in, length 31 in",
    'pants'    => "S: waist 28-30 in; M: waist 30-32 in; L: waist 32-34 in; XL: waist 34-36 in; XXL: waist 36-38 in",
    'dresses'  => "XS: bust 30-32 in, waist 24-26 in; S: bust 32-34 in, waist 26-28 in; M: bust 34-36 in, waist 28-30 in; L: bust 38-40 in, waist 30-32 in; XL: bust 40-42 in, waist 32-34 in",
];

const FIT_LABELS = [
    'fitted'  => 'fitted / snug',
    'regular' => 'regular / true to size',
    'loose'   => 'loose / oversized',
];

function size_fail(string $message, int $httpCode = 200): void
{
    if ($httpCode !== 200) {
        http_response_code($httpCode);
    }
    echo json_encode(['success' => false, 'error' => $message]);
    exit();
}

// --- Guards -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    size_fail('Method Not Allowed', 405);
}
if (!isLoggedIn()) {
    size_fail('Please log in to use the size finder.', 401);
}

$sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) $sentToken)) {
    size_fail('Invalid or missing security token. Please refresh the page.', 403);
}

// --- Input --------------------------------------------------------------
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    size_fail('Malformed request.');
}

$productId = (int) ($input['productId'] ?? 0);
$height    = (float) ($input['height'] ?? 0);   // cm
$weight    = (float) ($input['weight'] ?? 0);   // kg
$fit       = (string) ($input['fit'] ?? 'regular');

// Wide enough for kids and tall adults, narrow enough to stop nonsense.
if ($height < 80 || $height > 230) {
    size_fail('Please enter a height between 80 and 230 cm.');
}
if ($weight < 10 || $weight > 250) {
    size_fail('Please enter a weight between 10 and 250 kg.');
}
if (!isset(FIT_LABELS[$fit])) {
    $fit = 'regular';
}

// --- Product (server-side, so only its real sizes can be picked) ---------
$product = null;
if ($stmt = $conn->prepare("SELECT name, category, gender, available_sizes FROM products WHERE id = ? AND status = 'active'")) {
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$product) {
    size_fail('That product is not available.');
}

$sizes = array_values(array_filter(array_map('trim', explode(',', (string) ($product['available_sizes'] ?? '')))));
if (count($sizes) === 0) {
    size_fail('This item comes in one size only.');
}
if (count($sizes) === 1) {
    echo json_encode(['success' => true, 'size' => $sizes[0], 'reason' => 'This item comes in one size only.']);
    exit();
}

$guide = SIZE_GUIDE[$product['category']] ?? SIZE_GUIDE['t-shirts'];

/** The AI could not answer: answer from the formula instead, never an error. */
function size_fallback(string $why, array $product, float $height, float $weight, string $fit, array $sizes): void
{
    error_log('Size finder fell back to the formula: ' . $why);
    echo json_encode(['success' => true] + estimateSize((string) $product['category'], $height, $weight, $fit, $sizes));
    exit();
}
$fallback = fn(string $why) => size_fallback($why, $product, $height, $weight, $fit, $sizes);

$apiKey = defined('GEMINI_API_KEY') && GEMINI_API_KEY ? GEMINI_API_KEY : (getenv('GEMINI_API_KEY') ?: '');
if ($apiKey === '') {
    $fallback('GEMINI_API_KEY is not set');
}
// Over the hourly cap: the formula still gives them a size, just not the AI's.
if (!aiQuotaAllows($conn, 'size', 30)) {
    $fallback('hourly AI quota reached');
}

// --- Gemini -------------------------------------------------------------
$prompt = "You are a helpful fit assistant for an online clothing store in the Philippines.\n"
    . "Product: " . $product['name'] . " (category: " . $product['category']
    . ($product['gender'] ? ", for " . $product['gender'] : '') . ")\n"
    . "Sizes this product comes in: " . implode(', ', $sizes) . "\n"
    . "Store size guide: " . $guide . "\n\n"
    . "Customer: height " . round($height) . " cm, weight " . round($weight) . " kg, "
    . "prefers a " . FIT_LABELS[$fit] . " fit.\n\n"
    . "Estimate their body measurements from height and weight, compare with the size guide, "
    . "and pick the ONE best size from the sizes this product comes in. If they are between "
    . "sizes, size up for a loose fit and down for a fitted one. Reply as JSON with \"size\" "
    . "(exactly one of: " . implode(', ', $sizes) . ") and \"reason\" (ONE short, friendly English "
    . "sentence of at most 25 words, speaking to the customer as \"you\", mentioning the estimated "
    . "chest/waist measurement). Never comment on weight or body shape.";

$requestData = [
    'contents' => [[
        'role'  => 'user',
        'parts' => [['text' => $prompt]],
    ]],
    'generationConfig' => [
        'temperature'      => 0.2,
        'responseMimeType' => 'application/json',
        'responseSchema'   => [
            'type'       => 'OBJECT',
            'properties' => [
                'size'   => ['type' => 'STRING', 'enum' => $sizes],
                'reason' => ['type' => 'STRING'],
            ],
            'required' => ['size', 'reason'],
        ],
        // Thinking tokens would only slow a one-line answer down.
        'thinkingConfig' => ['thinkingBudget' => 0],
    ],
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://generativelanguage.googleapis.com/v1beta/models/' . SIZE_MODEL . ':generateContent?key=' . urlencode($apiKey),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($requestData),
    CURLOPT_TIMEOUT        => SIZE_TIMEOUT_SECS,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    $fallback('connection error: ' . $curlError);
}
if ($httpCode !== 200) {
    $errorData = json_decode((string) $response, true);
    $fallback('HTTP ' . $httpCode . ' ' . ($errorData['error']['message'] ?? ''));
}

// --- Parse + validate ----------------------------------------------------
$apiResponse = json_decode((string) $response, true);
$parsed = json_decode((string) ($apiResponse['candidates'][0]['content']['parts'][0]['text'] ?? ''), true);
$size   = is_array($parsed) ? trim((string) ($parsed['size'] ?? '')) : '';

// The schema enum should guarantee this; never trust it blindly.
$match = null;
foreach ($sizes as $s) {
    if (strcasecmp($s, $size) === 0) {
        $match = $s;
        break;
    }
}
if ($match === null) {
    $fallback('no valid size in the answer');
}

echo json_encode([
    'success' => true,
    'size'    => $match,
    'reason'  => mb_substr(trim((string) ($parsed['reason'] ?? '')), 0, 240),
]);
