<?php

declare(strict_types=1);

/**
 * AI Product Generator (admin-only) — Option B: AI drafts, admin approves.
 *
 * POST action=generate { prompt }  -> Gemini writes the product details (name,
 *   description, price, category, gender, colors, sizes), generates the print
 *   artwork on its own (the file a printer actually needs), then generates the
 *   catalog-style product photo FROM that artwork so the listing and the print
 *   file are the same design. Both are saved into images/products/. Nothing
 *   touches the database yet — the admin reviews/edits the draft in the UI first.
 *
 * POST action=save { ...fields }   -> validates everything against the same
 *   whitelists the manual Add Product form uses, then INSERTs into `products`.
 *
 * Standalone file on purpose: the existing manual add/edit/delete flow in
 * products.php is untouched.
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// A generate does up to three Gemini calls (details, artwork, photo) and on
// Windows max_execution_time counts wall clock, so the php.ini default of 120s
// can cut a slow run short. Scoped to this endpoint only.
@set_time_limit(240);

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admins only.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

// CSRF (same header pattern as the try-on endpoints).
$sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) $sentToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh the page.']);
    exit();
}

const AIP_TEXT_MODEL  = 'gemini-2.5-flash';
const AIP_IMAGE_MODEL = 'gemini-2.5-flash-image';
const AIP_TIMEOUT     = 60;
const AIP_IMG_DIR     = __DIR__ . '/../images/products/';

// Whitelists — identical to the manual Add Product form.
const AIP_CATEGORIES = ['t-shirts', 'hoodies', 'pants', 'dresses', 'accessories'];
const AIP_GENDERS    = ['mens', 'womens', 'kids'];
const AIP_COLORS     = ['Black', 'White', 'Navy', 'Red', 'Blue', 'Gray', 'Pink', 'Yellow', 'Green', 'Maroon', 'Brown', 'Orange', 'Purple', 'Beige'];
const AIP_SIZES      = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

function aip_fail(string $msg): void
{
    echo json_encode(['success' => false, 'error' => $msg]);
    exit();
}

function aip_gemini(string $model, array $body, string &$err): ?array
{
    $apiKey = defined('GEMINI_API_KEY') && GEMINI_API_KEY ? GEMINI_API_KEY : (getenv('GEMINI_API_KEY') ?: '');
    if ($apiKey === '') {
        $err = 'GEMINI_API_KEY is not configured in .env.';
        return null;
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . urlencode($apiKey),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_TIMEOUT        => AIP_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($cerr) { $err = 'Connection error: unable to reach the AI service.'; return null; }
    if ($code === 429) { $err = 'The AI is busy right now (rate limit). Please wait a moment and try again.'; return null; }
    if ($code !== 200) {
        $e = json_decode((string) $resp, true);
        $err = $e['error']['message'] ?? ('AI service error (HTTP ' . $code . ').');
        error_log('[ai-product] Gemini ' . $model . ' error (' . $code . '): ' . $err);
        return null;
    }
    return json_decode((string) $resp, true);
}

/**
 * Pull the first inline image out of a Gemini response.
 * Returns [base64, mimeType] or [null, null] when the model replied with no image.
 */
function aip_extract_image(?array $api): array
{
    foreach (($api['candidates'][0]['content']['parts'] ?? []) as $part) {
        if (isset($part['inlineData']['data'])) {
            return [$part['inlineData']['data'], $part['inlineData']['mimeType'] ?? 'image/png'];
        }
    }
    return [null, null];
}

/**
 * Decode and write a generated image into images/products/.
 * Returns the filename, or null if the bytes were unusable / unwritable.
 */
function aip_store_image(string $b64, string $mime, string $prefix): ?string
{
    $bytes = base64_decode($b64, true);
    if ($bytes === false || $bytes === '') {
        return null;
    }
    if (!is_dir(AIP_IMG_DIR) && !mkdir(AIP_IMG_DIR, 0755, true) && !is_dir(AIP_IMG_DIR)) {
        return null;
    }
    $ext      = ($mime === 'image/jpeg') ? 'jpg' : (($mime === 'image/webp') ? 'webp' : 'png');
    $filename = $prefix . time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    if (file_put_contents(AIP_IMG_DIR . $filename, $bytes) === false) {
        return null;
    }
    return $filename;
}

$input  = json_decode(file_get_contents('php://input'), true);
$action = is_array($input) ? (string) ($input['action'] ?? '') : '';

// ------------------------------------------------------------------
// GENERATE — draft only, nothing is written to the database.
// ------------------------------------------------------------------
if ($action === 'generate') {
    $prompt = trim((string) ($input['prompt'] ?? ''));
    if ($prompt === '') {
        aip_fail('Please describe the product to generate.');
    }
    if (mb_strlen($prompt) > 400) {
        $prompt = mb_substr($prompt, 0, 400);
    }

    // --- 1) Product details (cheap text call, strict JSON) ---
    $detailsPrompt = 'You are the merchandiser for "Thread & Press Hub", a Philippine online apparel store. '
        . 'Create ONE new product based on this idea: "' . $prompt . '".'
        . "\n\nRules:\n"
        . '- category must be one of: ' . implode(', ', AIP_CATEGORIES) . "\n"
        . '- gender must be one of: ' . implode(', ', AIP_GENDERS) . "\n"
        . '- colors: pick 2-4 from: ' . implode(', ', AIP_COLORS) . " (use these exact words)\n"
        . '- sizes: pick from: ' . implode(', ', AIP_SIZES) . " (empty array if category is accessories)\n"
        . '- price: a realistic PHP peso price for this store (kids items ~299-899, shirts ~349-699, hoodies ~899-1499, dresses ~999-2499, accessories ~299-899). Number only.'
        . "\n- name: catchy but short (max 40 chars). description: 1-2 enticing sentences.";

    $detailsBody = [
        'contents' => [[ 'role' => 'user', 'parts' => [['text' => $detailsPrompt]] ]],
        'generationConfig' => [
            'temperature'      => 0.8,
            'responseMimeType' => 'application/json',
            'responseSchema'   => [
                'type'       => 'OBJECT',
                'properties' => [
                    'name'        => ['type' => 'STRING'],
                    'description' => ['type' => 'STRING'],
                    'price'       => ['type' => 'NUMBER'],
                    'category'    => ['type' => 'STRING'],
                    'gender'      => ['type' => 'STRING'],
                    'colors'      => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                    'sizes'       => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                ],
                'required' => ['name', 'description', 'price', 'category', 'gender', 'colors', 'sizes'],
            ],
        ],
    ];

    $err = '';
    $api = aip_gemini(AIP_TEXT_MODEL, $detailsBody, $err);
    if ($api === null) aip_fail($err);

    $raw = $api['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $d   = json_decode((string) $raw, true);
    if (!is_array($d)) aip_fail('The AI returned unreadable product details. Please try again.');

    // Sanitize/whitelist the draft.
    $name     = mb_substr(trim((string) ($d['name'] ?? '')), 0, 100);
    $desc     = mb_substr(trim((string) ($d['description'] ?? '')), 0, 500);
    $price    = round(max(49, min(9999, (float) ($d['price'] ?? 0))), 2);
    $category = in_array($d['category'] ?? '', AIP_CATEGORIES, true) ? $d['category'] : 't-shirts';
    $gender   = in_array($d['gender'] ?? '', AIP_GENDERS, true) ? $d['gender'] : 'mens';
    $colors   = array_values(array_intersect(is_array($d['colors'] ?? null) ? $d['colors'] : [], AIP_COLORS));
    $sizes    = ($category === 'accessories') ? [] : array_values(array_intersect(is_array($d['sizes'] ?? null) ? $d['sizes'] : [], AIP_SIZES));
    if ($name === '') aip_fail('The AI did not produce a product name. Please try again.');
    if (empty($colors)) $colors = ['Black', 'White'];
    if (empty($sizes) && $category !== 'accessories') $sizes = ['S', 'M', 'L', 'XL'];

    // --- 2) Print artwork — the graphic on its own, no garment ---
    // This is the file an admin hands to a printer, so it must contain the
    // design and nothing else. Generated before the catalog photo so step 3
    // can be composed from it. Best-effort: if it fails we still ship a draft.
    $designPrompt = 'Flat 2D apparel print artwork for a product called "' . $name . '" — ' . $desc
        . ' Draw ONLY the graphic itself, as a standalone screen-print / DTG design: bold, clean, high contrast, '
        . 'sharp edges, centered and filling the frame, on a solid pure white background. '
        . 'Do NOT draw a t-shirt, garment, fabric, mockup, person, hanger, shadow, frame or border.';

    $designErr = '';
    $designApi = aip_gemini(AIP_IMAGE_MODEL, [
        'contents' => [[ 'role' => 'user', 'parts' => [['text' => $designPrompt]] ]],
        'generationConfig' => ['temperature' => 0.7],
    ], $designErr);

    [$designB64, $designMime] = aip_extract_image($designApi);
    $designFile = $designB64 !== null ? aip_store_image($designB64, $designMime, 'aidesign_') : null;
    if ($designFile === null) {
        error_log('[ai-product] print artwork unavailable: ' . ($designErr ?: 'model returned no image'));
    }

    // --- 3) Catalog product photo ---
    $imgPrompt = 'Professional e-commerce catalog photo of this product: "' . $name . '" — ' . $desc
        . ' Category: ' . $category . '. Show ONLY the product itself, laid flat or on an invisible mannequin, '
        . 'centered on a plain light-gray studio background (#f5f5f5). No people, no text, no watermark, no logo overlays.';

    $parts = [['text' => $imgPrompt]];
    if ($designFile !== null) {
        // Feed the artwork in so the listing photo shows the exact design the
        // print file contains — otherwise the two drift apart.
        $parts[0]['text'] .= ' Print the supplied artwork onto the garment, centered on the chest, '
            . 'reproducing it faithfully without altering its colors or composition.';
        $parts[] = ['inlineData' => ['mimeType' => $designMime, 'data' => $designB64]];
    }

    $api2 = aip_gemini(AIP_IMAGE_MODEL, [
        'contents' => [[ 'role' => 'user', 'parts' => $parts ]],
        'generationConfig' => ['temperature' => 0.6],
    ], $err);

    [$b64, $mime] = aip_extract_image($api2);

    // If composing from the artwork failed, retry the plain text-only photo so
    // the generator behaves exactly as it did before this feature existed.
    if ($b64 === null && count($parts) > 1) {
        $api2 = aip_gemini(AIP_IMAGE_MODEL, [
            'contents' => [[ 'role' => 'user', 'parts' => [['text' => $imgPrompt]] ]],
            'generationConfig' => ['temperature' => 0.6],
        ], $err);
        [$b64, $mime] = aip_extract_image($api2);
    }

    if ($b64 === null) aip_fail($err ?: 'The AI did not return a product image. Please try again.');

    $filename = aip_store_image($b64, $mime, 'ai_');
    if ($filename === null) aip_fail('Could not save the generated image.');

    echo json_encode([
        'success' => true,
        'draft'   => [
            'name'        => $name,
            'description' => $desc,
            'price'       => $price,
            'category'    => $category,
            'gender'      => $gender,
            'colors'      => $colors,
            'sizes'       => $sizes,
            'image'       => $filename,
            'image_url'   => '../images/products/' . rawurlencode($filename),
            'design'      => $designFile,
            'design_url'  => $designFile !== null ? '../images/products/' . rawurlencode($designFile) : null,
        ],
    ]);
    exit();
}

// ------------------------------------------------------------------
// SAVE — admin approved the (possibly edited) draft; insert it.
// ------------------------------------------------------------------
if ($action === 'save') {
    $name     = mb_substr(trim((string) ($input['name'] ?? '')), 0, 100);
    $desc     = mb_substr(trim((string) ($input['description'] ?? '')), 0, 500);
    $price    = round((float) ($input['price'] ?? 0), 2);
    $category = (string) ($input['category'] ?? '');
    $gender   = (string) ($input['gender'] ?? '');
    $stock    = max(0, (int) ($input['stock'] ?? 100));
    $image    = (string) ($input['image'] ?? '');
    $colors   = array_values(array_intersect(is_array($input['colors'] ?? null) ? $input['colors'] : [], AIP_COLORS));
    $sizes    = array_values(array_intersect(is_array($input['sizes'] ?? null) ? $input['sizes'] : [], AIP_SIZES));

    if ($name === '' || $price <= 0) aip_fail('A product name and a valid price are required.');
    if (!in_array($category, AIP_CATEGORIES, true)) aip_fail('Invalid category.');
    if (!in_array($gender, AIP_GENDERS, true)) aip_fail('Invalid gender.');
    if (empty($colors)) aip_fail('Pick at least one color.');
    if ($category === 'accessories') $sizes = [];

    // Only accept image files this generator created, and only from images/products/.
    if (!preg_match('/^ai_[0-9]+_[a-f0-9]+\.(png|jpg|webp)$/', $image) || !is_file(AIP_IMG_DIR . $image)) {
        aip_fail('Generated image not found — please generate again.');
    }

    // The print artwork is optional: older drafts and failed artwork calls have none.
    $design = (string) ($input['design'] ?? '');
    if ($design !== '' && (!preg_match('/^aidesign_[0-9]+_[a-f0-9]+\.(png|jpg|webp)$/', $design) || !is_file(AIP_IMG_DIR . $design))) {
        $design = '';
    }

    $colorsStr = implode(',', $colors);
    $sizesStr  = implode(',', $sizes);
    $status    = 'active';

    $cols  = ['name', 'description', 'price', 'category', 'gender', 'available_colors', 'available_sizes', 'image', 'status'];
    $types = 'ssdssssss';
    $vals  = [$name, $desc, $price, $category, $gender, $colorsStr, $sizesStr, $image, $status];

    if (productsHasStockColumn()) {
        $cols[] = 'stock';  $types .= 'i';  $vals[] = $stock;
    }
    if ($design !== '' && productsHasDesignColumn()) {
        $cols[] = 'design_asset';  $types .= 's';  $vals[] = $design;
    }

    $stmt = $conn->prepare(
        'INSERT INTO products (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')'
    );
    $stmt->bind_param($types, ...$vals);

    if (!$stmt->execute()) {
        $stmt->close();
        aip_fail('Failed to save the product. Please try again.');
    }
    $newId = $conn->insert_id;
    $stmt->close();

    if (function_exists('logAudit')) {
        logAudit('product_added', 'product', $newId, 'AI-generated product approved: ' . $name);
    }

    echo json_encode(['success' => true, 'id' => $newId]);
    exit();
}

aip_fail('Invalid action.');
