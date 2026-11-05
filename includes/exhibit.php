<?php
/**
 * Exhibit mode: one demo account that many visitors share on a booth device.
 *
 * That account gets a higher hourly AI limit, and keeps no saved looks, so one
 * visitor never sees another visitor's photo. It is set in Admin > Exhibit Mode
 * and stored on the Railway volume, so it can be switched off without a deploy.
 */

const EXHIBIT_LIMIT_DEFAULT = 100;
const EXHIBIT_LIMIT_MIN     = 15;    // the normal try-on limit
const EXHIBIT_LIMIT_MAX     = 500;   // each try-on costs money; never unlimited

function exhibitFile()
{
    return __DIR__ . '/../storage/payment-config/exhibit.json';
}

/** Error message for a bad hourly limit, or NULL when it is fine. */
function exhibitLimitError($value)
{
    $n = filter_var($value, FILTER_VALIDATE_INT);
    if ($n === false || $n < EXHIBIT_LIMIT_MIN || $n > EXHIBIT_LIMIT_MAX) {
        return 'The hourly limit must be a whole number from ' . EXHIBIT_LIMIT_MIN . ' to ' . EXHIBIT_LIMIT_MAX . '.';
    }
    return null;
}

/** ['user_id' => 0 when off, 'limit' => per hour] from saved data; bad data means off. */
function exhibitNormalize($saved)
{
    $off = ['user_id' => 0, 'limit' => EXHIBIT_LIMIT_DEFAULT];
    if (!is_array($saved)) {
        return $off;
    }
    $id = filter_var($saved['user_id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0 || exhibitLimitError($saved['limit'] ?? null) !== null) {
        return $off;
    }
    return ['user_id' => $id, 'limit' => (int) $saved['limit']];
}

/** The current settings, read once per request. Passing some replaces them for this request. */
function exhibitSettings(?array $set = null)
{
    static $settings = null;
    if ($set !== null) {
        $settings = exhibitNormalize($set);
    } elseif ($settings === null) {
        $raw = is_file(exhibitFile()) ? @file_get_contents(exhibitFile()) : false;
        $settings = exhibitNormalize(is_string($raw) ? json_decode($raw, true) : null);
    }
    return $settings;
}

/** Save the settings. Written to a temp file first so a reader never sees half a file. */
function exhibitSave(int $userId, int $limit)
{
    $file = exhibitFile();
    $dir  = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }
    $tmp = $file . '.tmp';
    $data = ['user_id' => $userId, 'limit' => $limit];
    if (@file_put_contents($tmp, json_encode($data), LOCK_EX) === false || !@rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }
    exhibitSettings($data);
    return true;
}

function exhibitOff()
{
    if (is_file(exhibitFile()) && !@unlink(exhibitFile())) {
        return false;
    }
    exhibitSettings(['user_id' => 0]);
    return true;
}

// ---------------------------------------------------------------------------
// Sample looks: before/after pairs the booth can show when the AI cannot run.
// ---------------------------------------------------------------------------
const EXHIBIT_SAMPLES_MAX      = 6;
const EXHIBIT_SAMPLE_MAX_BYTES = 4 * 1024 * 1024;   // two must fit Railway's 10 MB post limit
const EXHIBIT_SAMPLE_MAX_SIDE  = 6000;

function exhibitSamplesDir()
{
    return __DIR__ . '/../uploads/tryon-samples';
}

/** The pairs, oldest first: [['id', 'before', 'after'], ...], paths relative to the site root. */
function exhibitSamples()
{
    $pairs = [];
    foreach (glob(exhibitSamplesDir() . '/sample_*_before.*') ?: [] as $before) {
        if (!preg_match('/^sample_([0-9a-f]{16})_before\.(jpg|png)$/', basename($before), $m)) {
            continue;
        }
        $after = glob(exhibitSamplesDir() . '/sample_' . $m[1] . '_after.*') ?: [];
        if (!$after) {
            continue;   // half a pair shows nothing
        }
        $pairs[filemtime($before) . '_' . $m[1]] = [
            'id'     => $m[1],
            'before' => 'uploads/tryon-samples/' . basename($before),
            'after'  => 'uploads/tryon-samples/' . basename($after[0]),
        ];
    }
    ksort($pairs);
    return array_values($pairs);
}

/**
 * A JPEG without the parts that can say who or where: EXIF (GPS, device, date),
 * XMP, IPTC and comments. NULL when the bytes are not a well-formed JPEG.
 * shortcut: PNG text chunks stay; our phone photos are JPEG, and the upload form
 * redraws every image anyway. Strip them too if PNGs from elsewhere are ever used.
 */
function exhibitStripJpegMeta(string $bytes)
{
    if (substr($bytes, 0, 2) !== "\xFF\xD8") {
        return null;
    }
    $out = "\xFF\xD8";
    $i = 2;
    $n = strlen($bytes);
    while ($i + 4 <= $n) {
        if ($bytes[$i] !== "\xFF") {
            return null;
        }
        $marker = ord($bytes[$i + 1]);
        if ($marker === 0xDA) {   // the image data: everything after it is kept as is
            return $out . substr($bytes, $i);
        }
        $len = (ord($bytes[$i + 2]) << 8) | ord($bytes[$i + 3]);
        if ($len < 2 || $i + 2 + $len > $n) {
            return null;
        }
        if (!in_array($marker, [0xE1, 0xED, 0xFE], true)) {
            $out .= substr($bytes, $i, 2 + $len);
        }
        $i += 2 + $len;
    }
    return null;   // no image data
}

/** [bytes, extension] of an uploaded sample photo, or an error message string. */
function exhibitSampleRead($file, string $label)
{
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return "Choose the $label photo.";
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
        || ($file['size'] ?? 0) > EXHIBIT_SAMPLE_MAX_BYTES) {
        return "The $label photo is too large. The limit is 4 MB.";
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return "The $label photo could not be uploaded. Please try again.";
    }
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $size = @getimagesize($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true) || !$size) {
        return "The $label photo must be a JPEG or PNG image.";
    }
    if ($size[0] > EXHIBIT_SAMPLE_MAX_SIDE || $size[1] > EXHIBIT_SAMPLE_MAX_SIDE) {
        return "The $label photo is too large. Keep it under " . EXHIBIT_SAMPLE_MAX_SIDE . ' pixels on each side.';
    }
    $bytes = (string) file_get_contents($file['tmp_name']);
    if ($mime === 'image/png') {
        return [$bytes, 'png'];
    }
    $clean = exhibitStripJpegMeta($bytes);
    return $clean === null ? "The $label photo is a damaged JPEG." : [$clean, 'jpg'];
}

/** Stores one pair. Returns NULL when saved, or why not. */
function exhibitSampleAdd($beforeFile, $afterFile)
{
    if (count(exhibitSamples()) >= EXHIBIT_SAMPLES_MAX) {
        return 'You can keep up to ' . EXHIBIT_SAMPLES_MAX . ' samples. Delete one first.';
    }
    $before = exhibitSampleRead($beforeFile, 'before');
    if (is_string($before)) {
        return $before;
    }
    $after = exhibitSampleRead($afterFile, 'after');
    if (is_string($after)) {
        return $after;
    }
    $dir = exhibitSamplesDir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return 'Could not save. Check that the uploads folder is writable.';
    }
    $id = bin2hex(random_bytes(8));
    $a  = "$dir/sample_{$id}_after.{$after[1]}";
    $b  = "$dir/sample_{$id}_before.{$before[1]}";
    // The "before" goes last: it is what marks a finished pair.
    if (@file_put_contents($a, $after[0]) === false || @file_put_contents($b, $before[0]) === false) {
        @unlink($a);
        @unlink($b);
        return 'Could not save. Check that the uploads folder is writable.';
    }
    return null;
}

function exhibitSampleDelete(string $id)
{
    if (!preg_match('/^[0-9a-f]{16}$/', $id)) {
        return false;
    }
    $files = glob(exhibitSamplesDir() . '/sample_' . $id . '_*') ?: [];
    foreach ($files as $f) {
        @unlink($f);
    }
    return $files !== [];
}

/** True when the logged-in user is the booth's demo account. */
function isExhibitUser()
{
    $id = exhibitSettings()['user_id'];
    return $id > 0 && (int) ($_SESSION['user_id'] ?? 0) === $id;
}
