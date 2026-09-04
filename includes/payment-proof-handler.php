<?php
declare(strict_types=1);

/**
 * Manual QR Payment — secure proof-of-payment upload handling.
 *
 * Responsibilities:
 *   - Enforce size limit and a *server-side, sniffed* MIME allow-list
 *     (never trusts the browser-supplied type or the file extension).
 *   - Re-encode the image through GD, which strips ALL embedded metadata
 *     (EXIF, GPS, thumbnails) and neutralises any non-image payload smuggled
 *     inside a valid-looking file.
 *   - Store under storage/payment-proofs/{orderId}/ with a randomised
 *     filename, never inside a web-browsable directory.
 *
 * Requires includes/payment-config.php first.
 */

require_once __DIR__ . '/payment-config.php';

/**
 * Validate and securely store an uploaded payment screenshot.
 *
 * @param array<string,mixed> $file  One entry from $_FILES.
 * @param int                 $orderId
 * @return array{ok:bool, path:?string, error:?string}
 *   On success: path is relative to PAYMENT_PROOF_STORAGE ("{orderId}/{name}").
 */
function storePaymentProof(array $file, int $orderId): array
{
    // --- 1. Basic upload sanity ----------------------------------------
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'path' => null, 'error' => 'Invalid upload. Please try again.'];
    }
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['ok' => false, 'path' => null, 'error' => 'That file is too large. Maximum size is 5 MB.'];
        case UPLOAD_ERR_NO_FILE:
            return ['ok' => false, 'path' => null, 'error' => 'Please choose a payment screenshot to upload.'];
        default:
            return ['ok' => false, 'path' => null, 'error' => 'Upload failed. Please try again.'];
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > PAYMENT_PROOF_MAX_BYTES) {
        return ['ok' => false, 'path' => null, 'error' => 'That file is too large. Maximum size is 5 MB.'];
    }

    $tmp = $file['tmp_name'] ?? '';
    if (!is_string($tmp) || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'path' => null, 'error' => 'Invalid upload source. Please try again.'];
    }

    // --- 2. Real MIME via sniffing + image validation ------------------
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string)$finfo->file($tmp);
    if (!isset(PAYMENT_PROOF_ALLOWED[$mime])) {
        return ['ok' => false, 'path' => null, 'error' => 'Only PNG, JPG, or WEBP images are accepted.'];
    }

    // getimagesize is a second, independent confirmation that this really
    // is a decodable raster image (and gives us its true type).
    $info = @getimagesize($tmp);
    if ($info === false) {
        return ['ok' => false, 'path' => null, 'error' => 'That file is not a valid image.'];
    }

    // --- 3. Re-encode through GD to strip EXIF / metadata --------------
    $image = paymentDecodeImage($tmp, $mime);
    if ($image === null) {
        return ['ok' => false, 'path' => null, 'error' => 'We could not process that image. Please upload a standard PNG, JPG, or WEBP.'];
    }

    // --- 4. Build a safe destination -----------------------------------
    $ext     = PAYMENT_PROOF_ALLOWED[$mime];
    $destDir = PAYMENT_PROOF_STORAGE . DIRECTORY_SEPARATOR . $orderId;
    if (!is_dir($destDir) && !@mkdir($destDir, 0775, true) && !is_dir($destDir)) {
        imagedestroy($image);
        return ['ok' => false, 'path' => null, 'error' => 'Server storage is unavailable right now. Please try again later.'];
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destAbs  = $destDir . DIRECTORY_SEPARATOR . $filename;

    $saved = paymentEncodeImage($image, $destAbs, $mime);
    imagedestroy($image);

    if (!$saved) {
        return ['ok' => false, 'path' => null, 'error' => 'We could not save your screenshot. Please try again.'];
    }
    @chmod($destAbs, 0644);

    // Path stored in DB is relative to the storage root (portable, no leak
    // of the absolute server path).
    return ['ok' => true, 'path' => $orderId . '/' . $filename, 'error' => null];
}

/**
 * Decode an uploaded image into a GD resource based on its sniffed MIME.
 *
 * @return \GdImage|null
 */
function paymentDecodeImage(string $path, string $mime)
{
    $image = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png'  => @imagecreatefrompng($path),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default      => false,
    };
    return $image instanceof \GdImage ? $image : null;
}

/**
 * Encode a GD image to disk in the same format, preserving PNG transparency.
 *
 * @param \GdImage $image
 */
function paymentEncodeImage($image, string $destAbs, string $mime): bool
{
    switch ($mime) {
        case 'image/jpeg':
            return imagejpeg($image, $destAbs, 90);
        case 'image/png':
            imagealphablending($image, false);
            imagesavealpha($image, true);
            return imagepng($image, $destAbs, 6);
        case 'image/webp':
            return function_exists('imagewebp') && imagewebp($image, $destAbs, 90);
        default:
            return false;
    }
}

/**
 * Resolve a stored relative proof path to a safe absolute path, guarding
 * against directory traversal. Returns null if it escapes the storage root
 * or does not exist.
 */
function paymentResolveProofPath(?string $relative): ?string
{
    if ($relative === null || $relative === '') {
        return null;
    }
    // Reject any traversal attempt outright.
    if (str_contains($relative, '..') || str_contains($relative, "\0")) {
        return null;
    }
    $abs  = PAYMENT_PROOF_STORAGE . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $real = realpath($abs);
    $root = realpath(PAYMENT_PROOF_STORAGE);
    if ($real === false || $root === false || !str_starts_with($real, $root)) {
        return null;
    }
    return $real;
}
