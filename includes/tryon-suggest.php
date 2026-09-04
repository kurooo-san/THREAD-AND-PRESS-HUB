<?php

declare(strict_types=1);

/**
 * AI Stylist (Auto Mode) endpoint.
 *
 * Receives a webcam frame (base64) and asks Gemini's vision model to pick the
 * products from our catalog that best suit the person — based on colour, fit
 * and overall style. Returns a short ranked list of suggestions (no image is
 * generated here; that stays in tryon-ajax.php).
 *
 * Security: login-gated, POST-only, CSRF-checked. The catalog is loaded
 * server-side so the model only ranks real, active products.
 */

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Text + vision model (cheap; analysis only — not image generation).
const SUGGEST_MODEL        = 'gemini-2.5-flash';
const SUGGEST_TIMEOUT_SECS = 45;
const SUGGEST_MAX_BYTES    = 6 * 1024 * 1024;
const SUGGEST_TOP_N        = 3;

/** Emit a JSON error and stop. */
function suggest_fail(string $message, int $httpCode = 200): void
{
    if ($httpCode !== 200) {
        http_response_code($httpCode);
    }
    echo json_encode(['success' => false, 'error' => $message]);
    exit();
}

// --- Guards -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    suggest_fail('Method Not Allowed', 405);
}
if (!isLoggedIn()) {
    suggest_fail('Please log in to use the AI Stylist.', 401);
}

$apiKey = defined('GEMINI_API_KEY') && GEMINI_API_KEY ? GEMINI_API_KEY : (getenv('GEMINI_API_KEY') ?: '');
if ($apiKey === '') {
    suggest_fail('AI Stylist is not configured. Please set GEMINI_API_KEY in your .env file.');
}

$sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) $sentToken)) {
    suggest_fail('Invalid or missing security token. Please refresh the page.', 403);
}

// --- Input --------------------------------------------------------------
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    suggest_fail('Malformed request.');
}

$personImage = (string) ($input['personImage'] ?? '');

// Optional gender filter (matches shop.php values). Empty = all genders.
$gender = strtolower(trim((string) ($input['gender'] ?? '')));
if (!in_array($gender, ['mens', 'womens', 'kids'], true)) {
    $gender = '';
}

$personBytes = suggest_decode_image($personImage, $personMime);
if ($personBytes === null) {
    suggest_fail('Could not read the camera frame. Please try again.');
}
if (strlen($personBytes) > SUGGEST_MAX_BYTES) {
    suggest_fail('Camera frame is too large. Please try again.');
}

// --- Load the catalog server-side (same wearable set as the try-on page) -
$wearableCategories = ['t-shirts', 'hoodies', 'dresses', 'pants'];
$placeholders = implode(',', array_fill(0, count($wearableCategories), '?'));

$sql = "SELECT id, name, category
        FROM products
        WHERE status = 'active' AND category IN ($placeholders) AND image <> ''";

$bindTypes  = str_repeat('s', count($wearableCategories));
$bindParams = $wearableCategories;

// Restrict to the chosen gender when one was selected.
if ($gender !== '') {
    $sql .= " AND gender = ?";
    $bindTypes .= 's';
    $bindParams[] = $gender;
}
$sql .= " ORDER BY category, name";

$catalog = [];
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param($bindTypes, ...$bindParams);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $catalog[(int) $row['id']] = [
            'id'       => (int) $row['id'],
            'name'     => $row['name'],
            'category' => $row['category'],
        ];
    }
    $stmt->close();
}

if (count($catalog) === 0) {
    suggest_fail('No products are available to suggest right now.');
}

// Build a compact catalog listing for the prompt.
$catalogLines = [];
foreach ($catalog as $p) {
    $catalogLines[] = '- id ' . $p['id'] . ': ' . $p['name'] . ' (' . $p['category'] . ')';
}
$catalogText = implode("\n", $catalogLines);

// --- Build the Gemini request -------------------------------------------
$prompt = "You are a friendly personal fashion stylist for an online clothing store. "
    . "The image is a photo of a customer. Based ONLY on style, colour harmony with their "
    . "complexion, body proportions and overall vibe, choose the " . SUGGEST_TOP_N . " items "
    . "from the catalog below that would suit this person best, ranked best first.\n\n"
    . "Be tasteful and positive — comment on style and fit, never on weight, attractiveness, "
    . "or anything sensitive. Only choose items from this catalog (use their exact id):\n\n"
    . $catalogText . "\n\n"
    . "Return your answer as JSON: an array \"suggestions\" of objects with \"id\" (integer from "
    . "the catalog) and \"reason\" (one short, friendly sentence in English explaining why it "
    . "suits them). Do not include items that are not in the catalog.";

$requestData = [
    'contents' => [[
        'role'  => 'user',
        'parts' => [
            ['text' => $prompt],
            ['inlineData' => ['mimeType' => $personMime, 'data' => base64_encode($personBytes)]],
        ],
    ]],
    'generationConfig' => [
        'temperature'      => 0.5,
        'responseMimeType' => 'application/json',
        'responseSchema'   => [
            'type'       => 'OBJECT',
            'properties' => [
                'suggestions' => [
                    'type'  => 'ARRAY',
                    'items' => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'id'     => ['type' => 'INTEGER'],
                            'reason' => ['type' => 'STRING'],
                        ],
                        'required' => ['id', 'reason'],
                    ],
                ],
            ],
            'required' => ['suggestions'],
        ],
    ],
];

$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/'
    . SUGGEST_MODEL . ':generateContent?key=' . urlencode($apiKey);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($requestData),
    CURLOPT_TIMEOUT        => SUGGEST_TIMEOUT_SECS,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// --- Handle transport / API errors --------------------------------------
if ($curlError) {
    suggest_fail('Connection error: unable to reach the AI service. Please try again.');
}
if ($httpCode === 429) {
    suggest_fail('The AI service is busy right now (rate limit). Please wait a moment and try again.');
}
if ($httpCode !== 200) {
    $errorData = json_decode((string) $response, true);
    $msg = $errorData['error']['message'] ?? ('The AI service returned an error (HTTP ' . $httpCode . ').');
    error_log('AI Stylist API error (' . $httpCode . '): ' . $msg);
    suggest_fail('Could not get suggestions: ' . $msg);
}

// --- Parse + validate ----------------------------------------------------
$apiResponse = json_decode((string) $response, true);
$rawText = $apiResponse['candidates'][0]['content']['parts'][0]['text'] ?? '';
$parsed  = json_decode((string) $rawText, true);

$rawSuggestions = is_array($parsed) && isset($parsed['suggestions']) && is_array($parsed['suggestions'])
    ? $parsed['suggestions']
    : [];

$suggestions = [];
$seen = [];
foreach ($rawSuggestions as $s) {
    $id = isset($s['id']) ? (int) $s['id'] : 0;
    if ($id <= 0 || !isset($catalog[$id]) || isset($seen[$id])) {
        continue; // drop hallucinated / duplicate ids
    }
    $seen[$id] = true;
    $suggestions[] = [
        'id'     => $id,
        'name'   => $catalog[$id]['name'],
        'reason' => trim((string) ($s['reason'] ?? '')),
    ];
    if (count($suggestions) >= SUGGEST_TOP_N) {
        break;
    }
}

if (count($suggestions) === 0) {
    suggest_fail('The AI could not pick a suggestion. Try a clearer, well-lit photo facing the camera.');
}

echo json_encode(['success' => true, 'suggestions' => $suggestions]);

// =======================================================================
// Helpers
// =======================================================================

/**
 * Decode a data URL or bare base64 string into raw image bytes.
 * Sets $mime to the detected MIME type. Returns null on failure.
 */
function suggest_decode_image(string $value, ?string &$mime): ?string
{
    $mime = 'image/jpeg';
    if ($value === '') {
        return null;
    }
    if (preg_match('#^data:(image/[a-zA-Z0-9.+-]+);base64,(.*)$#s', $value, $m)) {
        $mime  = strtolower($m[1]);
        $value = $m[2];
    }
    $bytes = base64_decode($value, true);
    if ($bytes === false || $bytes === '') {
        return null;
    }
    $info = @getimagesizefromstring($bytes);
    if ($info === false || empty($info['mime'])) {
        return null;
    }
    $mime = $info['mime'];
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return null;
    }
    return $bytes;
}
