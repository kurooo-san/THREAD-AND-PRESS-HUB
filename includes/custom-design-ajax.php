<?php
require __DIR__ . '/config.php';
require __DIR__ . '/apparel-config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to use the design tool.']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = (int) $_SESSION['user_id'];

switch ($action) {
    case 'save':
        handleSave($conn, $userId);
        break;
    case 'upload_asset':
        handleUploadAsset($userId);
        break;
    case 'ai_generate':
        handleAiGenerate($userId);
        break;
    case 'list':
        handleList($conn, $userId);
        break;
    case 'get':
        handleGet($conn, $userId);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}

/**
 * Validates and stores a user-uploaded image (logo/artwork) entirely server-side.
 * Never trusts the browser: re-checks MIME from bytes, enforces a size limit, and
 * re-encodes through GD so EXIF/metadata is stripped. Returns a same-origin URL the
 * client can safely draw onto the design canvas without tainting it.
 */
function handleUploadAsset($userId)
{
    if (!isset($_FILES['asset']) || $_FILES['asset']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Upload failed. Please try again.']);
        return;
    }

    $file = $_FILES['asset'];

    // Size limit: 5 MB.
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Image must be less than 5MB.']);
        return;
    }

    // Detect the real MIME type from the file bytes (never from the browser).
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        echo json_encode(['success' => false, 'message' => 'Only PNG, JPG, and WEBP images are allowed.']);
        return;
    }

    // Confirm it is a genuinely decodable image and clamp dimensions.
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || $info[0] < 1 || $info[1] < 1 || $info[0] > 6000 || $info[1] > 6000) {
        echo json_encode(['success' => false, 'message' => 'Invalid or oversized image.']);
        return;
    }

    $uploadDir = __DIR__ . '/../uploads/design_assets/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = $allowed[$mime];
    $filename = 'asset_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $filePath = $uploadDir . $filename;

    // Re-encode via GD to strip EXIF/metadata and neutralise any embedded payload.
    $saved = reencodeImage($file['tmp_name'], $mime, $filePath);
    if (!$saved) {
        echo json_encode(['success' => false, 'message' => 'Could not process the image.']);
        return;
    }

    echo json_encode(['success' => true, 'url' => 'uploads/design_assets/' . $filename]);
}

/**
 * AI Design Generator. Takes a short text prompt and asks Google's Gemini image
 * model to generate an apparel graphic, isolated on a plain background. The PNG
 * is saved server-side (same dir as uploads) and a same-origin URL is returned,
 * so the client can drop it onto the design canvas exactly like an upload.
 */
function handleAiGenerate($userId)
{
    $apiKey = defined('GEMINI_API_KEY') && GEMINI_API_KEY ? GEMINI_API_KEY : (getenv('GEMINI_API_KEY') ?: '');
    if ($apiKey === '') {
        echo json_encode(['success' => false, 'message' => 'AI design is not configured. Please set GEMINI_API_KEY in your .env file.']);
        return;
    }

    $prompt = trim((string) ($_POST['prompt'] ?? ''));
    if ($prompt === '') {
        echo json_encode(['success' => false, 'message' => 'Please describe the design you want.']);
        return;
    }
    if (mb_strlen($prompt) > 500) {
        $prompt = mb_substr($prompt, 0, 500);
    }

    $fullPrompt = 'Create a single, high-quality apparel graphic / print design based on this idea: "'
        . $prompt . '". Make it bold, clean and well-composed, suitable for printing on a t-shirt. '
        . 'Center the artwork on a plain solid white background. Do NOT show a shirt, person, mockup, '
        . 'photo frame, or any watermark — output ONLY the final standalone graphic.';

    $model = 'gemini-2.5-flash-image';
    $requestData = [
        'contents' => [[
            'role'  => 'user',
            'parts' => [['text' => $fullPrompt]],
        ]],
        'generationConfig' => ['temperature' => 0.7],
    ];

    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . $model . ':generateContent?key=' . urlencode($apiKey);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($requestData),
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo json_encode(['success' => false, 'message' => 'Connection error: unable to reach the AI service.']);
        return;
    }
    if ($httpCode === 429) {
        echo json_encode(['success' => false, 'message' => 'The AI is busy right now (rate limit). Please wait a moment and try again.']);
        return;
    }
    if ($httpCode !== 200) {
        $err = json_decode((string) $response, true);
        $msg = $err['error']['message'] ?? ('AI service error (HTTP ' . $httpCode . ').');
        error_log('AI design error (' . $httpCode . '): ' . $msg);
        echo json_encode(['success' => false, 'message' => 'AI design failed: ' . $msg]);
        return;
    }

    $api   = json_decode((string) $response, true);
    $parts = $api['candidates'][0]['content']['parts'] ?? [];
    $b64   = null;
    $mime  = 'image/png';
    foreach ($parts as $part) {
        if (isset($part['inlineData']['data'])) {
            $b64  = $part['inlineData']['data'];
            $mime = $part['inlineData']['mimeType'] ?? 'image/png';
            break;
        }
    }
    if ($b64 === null) {
        $finish = $api['candidates'][0]['finishReason'] ?? '';
        if ($finish === 'SAFETY' || $finish === 'PROHIBITED_CONTENT') {
            echo json_encode(['success' => false, 'message' => 'That idea was blocked by the AI. Please try a different prompt.']);
            return;
        }
        echo json_encode(['success' => false, 'message' => 'The AI did not return an image. Please rephrase and try again.']);
        return;
    }

    $bytes = base64_decode($b64, true);
    if ($bytes === false || $bytes === '') {
        echo json_encode(['success' => false, 'message' => 'The AI returned invalid image data.']);
        return;
    }

    $uploadDir = __DIR__ . '/../uploads/design_assets/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = ($mime === 'image/jpeg') ? 'jpg' : (($mime === 'image/webp') ? 'webp' : 'png');
    $filename = 'ai_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (file_put_contents($uploadDir . $filename, $bytes) === false) {
        echo json_encode(['success' => false, 'message' => 'Could not save the generated image.']);
        return;
    }

    echo json_encode(['success' => true, 'url' => 'uploads/design_assets/' . $filename]);
}

/** Re-encodes an image through GD, dropping all metadata. Returns true on success. */
function reencodeImage($srcPath, $mime, $destPath)
{
    if (!function_exists('imagecreatefromstring')) {
        // GD unavailable: fall back to a raw copy (still randomized + validated above).
        return copy($srcPath, $destPath);
    }

    switch ($mime) {
        case 'image/png':  $img = @imagecreatefrompng($srcPath); break;
        case 'image/jpeg': $img = @imagecreatefromjpeg($srcPath); break;
        case 'image/webp': $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : false; break;
        default:           $img = false;
    }
    if (!$img) {
        return copy($srcPath, $destPath);
    }

    // Preserve transparency for PNG/WEBP.
    imagealphablending($img, false);
    imagesavealpha($img, true);

    switch ($mime) {
        case 'image/png':  $ok = imagepng($img, $destPath, 6); break;
        case 'image/jpeg': $ok = imagejpeg($img, $destPath, 90); break;
        case 'image/webp': $ok = function_exists('imagewebp') ? imagewebp($img, $destPath, 90) : false; break;
        default:           $ok = false;
    }
    imagedestroy($img);
    return (bool) $ok;
}

function handleSave($conn, $userId) {
    $productType = $conn->real_escape_string(trim($_POST['product_type'] ?? 'tshirt'));
    $notes = $conn->real_escape_string(trim($_POST['notes'] ?? ''));
    $designImage = $_POST['design_image'] ?? '';
    $designImageBack = $_POST['design_image_back'] ?? '';
    $designData = $_POST['design_data'] ?? '{}';

    if (empty($designImage)) {
        echo json_encode(['success' => false, 'message' => 'No design image provided.']);
        return;
    }

    // Validate product type against the data-driven apparel config.
    $allowedTypes = getApparelTypeKeys();
    if (!in_array($productType, $allowedTypes, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid apparel type.']);
        return;
    }

    // Validate the design image is a valid base64 data URI
    if (strpos($designImage, 'data:image/') !== 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid image format.']);
        return;
    }

    $uploadDir = __DIR__ . '/../uploads/designs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Save front image
    $dbImagePath = saveDesignImage($designImage, $uploadDir, $userId, 'front');
    if (!$dbImagePath) {
        echo json_encode(['success' => false, 'message' => 'Failed to save front design image.']);
        return;
    }

    // Save back image (if provided)
    $dbImageBackPath = '';
    if (!empty($designImageBack) && strpos($designImageBack, 'data:image/') === 0) {
        $dbImageBackPath = saveDesignImage($designImageBack, $uploadDir, $userId, 'back');
        if (!$dbImageBackPath) {
            $dbImageBackPath = '';
        }
    }

    // Print-ready artwork: the design on its own, transparent, cropped to the
    // printable area. design_image is a MOCKUP (garment + artwork flattened)
    // and cannot be sent to a printer. Optional -- a design with no artwork on
    // a side simply has no print file for it.
    $printFrontPath = '';
    $printBackPath  = '';
    $printFrontRaw = $_POST['print_front'] ?? '';
    $printBackRaw  = $_POST['print_back'] ?? '';
    if (!empty($printFrontRaw) && strpos($printFrontRaw, 'data:image/') === 0) {
        $printFrontPath = saveDesignImage($printFrontRaw, $uploadDir, $userId, 'print-front') ?: '';
    }
    if (!empty($printBackRaw) && strpos($printBackRaw, 'data:image/') === 0) {
        $printBackPath = saveDesignImage($printBackRaw, $uploadDir, $userId, 'print-back') ?: '';
    }

    // Add design_image_back column if it doesn't exist
    $conn->query("ALTER TABLE custom_designs ADD COLUMN IF NOT EXISTS design_image_back LONGTEXT DEFAULT NULL AFTER design_image");
    // Same for the print files, so a missed migration does not break saving.
    $conn->query("ALTER TABLE custom_designs ADD COLUMN IF NOT EXISTS print_front VARCHAR(255) DEFAULT NULL AFTER design_image_back");
    $conn->query("ALTER TABLE custom_designs ADD COLUMN IF NOT EXISTS print_back VARCHAR(255) DEFAULT NULL AFTER print_front");

    $stmt = $conn->prepare("INSERT INTO custom_designs (user_id, product_type, design_image, design_image_back, print_front, print_back, design_data, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("isssssss", $userId, $productType, $dbImagePath, $dbImageBackPath, $printFrontPath, $printBackPath, $designData, $notes);

    if ($stmt->execute()) {
        $designId = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Design submitted successfully!',
            'design_id' => $designId
        ]);
    } else {
        // Clean up uploaded files on DB failure
        @unlink($uploadDir . basename($dbImagePath));
        if ($dbImageBackPath) @unlink($uploadDir . basename($dbImageBackPath));
        if ($printFrontPath)  @unlink($uploadDir . basename($printFrontPath));
        if ($printBackPath)   @unlink($uploadDir . basename($printBackPath));
        echo json_encode(['success' => false, 'message' => 'Failed to save design. Please try again.']);
    }
    $stmt->close();
}

function saveDesignImage($imageDataUri, $uploadDir, $userId, $side) {
    $imageData = explode(',', $imageDataUri, 2);
    if (count($imageData) !== 2) return false;

    $decoded = base64_decode($imageData[1], true);
    if ($decoded === false) return false;

    $ext = 'png';
    if (strpos($imageData[0], 'jpeg') !== false) $ext = 'jpg';
    elseif (strpos($imageData[0], 'webp') !== false) $ext = 'webp';

    $filename = 'design_' . $userId . '_' . $side . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filePath = $uploadDir . $filename;

    if (!file_put_contents($filePath, $decoded)) return false;

    return 'uploads/designs/' . $filename;
}

function handleList($conn, $userId) {
    // design_data is included so a card can show the colour, size, print size
    // and quantity the customer actually chose -- and carry them into the
    // order instead of silently resetting everything to white / M / 1.
    $stmt = $conn->prepare("SELECT id, product_type, design_image, design_data, notes, status, created_at FROM custom_designs WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    // The account type decides the discount; a card must never take it from
    // the browser, same rule the order page applies.
    $accountType = 'regular';
    $uStmt = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
    if ($uStmt) {
        $uStmt->bind_param('i', $userId);
        $uStmt->execute();
        if ($u = $uStmt->get_result()->fetch_assoc()) {
            $accountType = (string) $u['user_type'];
        }
        $uStmt->close();
    }
    $discountType = in_array($accountType, ['pwd', 'senior'], true) ? $accountType : 'regular';

    $root = dirname(__DIR__);

    $designs = [];
    while ($row = $result->fetch_assoc()) {
        $data = json_decode((string) ($row['design_data'] ?? ''), true);
        if (!is_array($data)) { $data = []; }

        $row['apparel_color'] = isset($data['apparelColor']) && preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $data['apparelColor'])
            ? (string) $data['apparelColor'] : '#FFFFFF';
        $row['size']       = in_array($data['size'] ?? '', ['XS','S','M','L','XL','2XL'], true) ? (string) $data['size'] : 'M';
        $row['print_size'] = in_array($data['printSize'] ?? '', ['small','medium','large','full'], true) ? (string) $data['printSize'] : 'medium';
        $row['quantity']   = max(1, min(100, (int) ($data['quantity'] ?? 1)));
        $row['elements']   = (int) ($data['elementsCount'] ?? 0);

        $price = customDesignPrice(
            (string) $row['product_type'],
            $row['print_size'],
            $row['quantity'],
            (int) ($data['colorsUsed'] ?? 1),
            $discountType
        );
        $row['price_total']    = $price['total'];
        $row['price_unit']     = $price['unit'];
        $row['discount_type']  = $discountType;
        $row['colors_used']    = $price['colorsUsed'];

        // Tell the page up front whether the artwork file is still on disk, so
        // it can say "preview unavailable" rather than showing a placeholder
        // that looks like a design in its own right.
        $img = (string) ($row['design_image'] ?? '');
        $row['image_missing'] = ($img === '' || !is_file($root . DIRECTORY_SEPARATOR . $img));

        $designs[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'designs' => $designs]);
}

function handleGet($conn, $userId) {
    $designId = (int) ($_GET['id'] ?? 0);

    $stmt = $conn->prepare("SELECT * FROM custom_designs WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $designId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $design = $result->fetch_assoc();
    $stmt->close();

    if ($design) {
        echo json_encode(['success' => true, 'design' => $design]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Design not found.']);
    }
}
