<?php
declare(strict_types=1);

/**
 * Store branding: name, logos and app icons, editable in admin/branding.php.
 *
 * Every page, email, invoice, chatbot prompt and the PWA manifest reads the
 * brand from here, so a new owner changes it once in the admin panel.
 *
 * Settings and files live in uploads/branding/, which is on the Railway
 * volume (survives redeploys). Apache denies *.json under uploads/, so
 * brand.json is never fetched by URL. With no brand.json, the shipped
 * Thread & Press Hub defaults are used.
 */

define('BRAND_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'branding');
define('BRAND_CONFIG_FILE', BRAND_DIR . DIRECTORY_SEPARATOR . 'brand.json');
define('BRAND_URLBASE', 'uploads/branding/');

const BRAND_DEFAULTS = [
    'name'       => 'Thread & Press Hub', // full name: titles, emails, invoices, AI prompts
    'short_name' => 'Thread & Press',     // navbar, admin sidebar, app name on a phone
    'tagline'    => 'HUB',                // small label under the navbar name (may be empty)
    'invoice_tagline' => 'Premium Apparel & Custom Designs', // under the name on PDF invoices (may be empty)
    'logo'       => '',                   // file in uploads/branding/, '' = shipped logo
    'logo_dark'  => '',                   // logo for dark backgrounds, '' = use 'logo'
    'icon'       => '',                   // icon set version id, '' = shipped icons
];

/** Shipped files used until an admin uploads replacements. */
const BRAND_DEFAULT_LOGO      = 'images/logo/logo_sm.png';
const BRAND_DEFAULT_LOGO_DARK = 'images/logo/logo_white_sm.png';
const BRAND_DEFAULT_ICONS     = [
    '32'       => 'images/logo/logo_sm.png',
    '180'      => 'images/icons/apple-touch-icon.png',
    '192'      => 'images/icons/icon-192.png',
    '512'      => 'images/icons/icon-512.png',
    'maskable' => 'images/icons/maskable-512.png',
];

/**
 * Current settings (defaults merged with brand.json). Pass $replace after a
 * save so the rest of the request sees the new values.
 *
 * @return array<string,string>
 */
function brandSettings(?array $replace = null): array
{
    static $settings = null;
    if ($replace !== null) {
        $settings = $replace;
    }
    if ($settings === null) {
        $settings = BRAND_DEFAULTS;
        $raw  = is_file(BRAND_CONFIG_FILE) ? @file_get_contents(BRAND_CONFIG_FILE) : false;
        $data = $raw !== false ? json_decode($raw, true) : null;
        if (is_array($data)) {
            foreach (BRAND_DEFAULTS as $key => $_) {
                if (isset($data[$key]) && is_string($data[$key])) {
                    $settings[$key] = $data[$key];
                }
            }
        }
        // Names can never end up blank, whatever is in the file.
        foreach (['name', 'short_name'] as $key) {
            if (trim($settings[$key]) === '') {
                $settings[$key] = BRAND_DEFAULTS[$key];
            }
        }
    }
    return $settings;
}

/** One setting as plain text, e.g. brand('name'). Escape it when printing HTML. */
function brand(string $key): string
{
    return brandSettings()[$key] ?? '';
}

/** brand() escaped for HTML. */
function brandHtml(string $key): string
{
    return htmlspecialchars(brand($key), ENT_QUOTES, 'UTF-8');
}

/** Seeded FAQ answers name the original store; show the current name instead. */
function brandCurrentName(string $text): string
{
    return preg_replace_callback('/Thread\s*(?:and|&amp;|&)\s*Press(?:\s*Hub)?/', static fn(): string => brand('name'), $text) ?? $text;
}

/** Save settings to brand.json. */
function brandSave(array $settings): bool
{
    if (!is_dir(BRAND_DIR) && !@mkdir(BRAND_DIR, 0775, true) && !is_dir(BRAND_DIR)) {
        return false;
    }
    $clean = [];
    foreach (BRAND_DEFAULTS as $key => $default) {
        $clean[$key] = isset($settings[$key]) && is_string($settings[$key]) ? $settings[$key] : $default;
    }
    $json = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents(BRAND_CONFIG_FILE, $json, LOCK_EX) === false) {
        return false;
    }
    brandSettings($clean);
    return true;
}

/** Path (from the web root) of a stored branding file, or $fallback. */
function brandFileUrl(string $file, string $fallback): string
{
    // basename(): the value comes from brand.json, never trust it as a path.
    $file = basename($file);
    return $file !== '' && is_file(BRAND_DIR . DIRECTORY_SEPARATOR . $file) ? BRAND_URLBASE . rawurlencode($file) : $fallback;
}

/**
 * Logo path from the web root. $dark = the version for dark backgrounds.
 * An uploaded logo with no dark version is used for both, so the old
 * brand's logo never shows next to the new one.
 */
function brandLogoUrl(bool $dark = false): string
{
    $s = brandSettings();
    if ($dark && $s['logo_dark'] !== '') {
        return brandFileUrl($s['logo_dark'], BRAND_DEFAULT_LOGO_DARK);
    }
    if ($s['logo'] !== '') {
        return brandFileUrl($s['logo'], $dark ? BRAND_DEFAULT_LOGO_DARK : BRAND_DEFAULT_LOGO);
    }
    return $dark ? BRAND_DEFAULT_LOGO_DARK : BRAND_DEFAULT_LOGO;
}

/** App icon path from the web root; $size is a key of BRAND_DEFAULT_ICONS. */
function brandIconUrl(string $size): string
{
    $fallback = BRAND_DEFAULT_ICONS[$size] ?? BRAND_DEFAULT_ICONS['192'];
    $version  = brandSettings()['icon'];
    if ($version === '' || !preg_match('/^[a-f0-9]{12}$/', $version)) {
        return $fallback;
    }
    return brandFileUrl('icon-' . $version . '-' . $size . '.png', $fallback);
}

/**
 * The light + dark logo pair the site swaps with CSS (.logo-light/.logo-dark).
 * $base is the path back to the web root ('' or '../').
 */
function brandLogoImgs(string $class, string $base = '', string $alt = ''): string
{
    $alt = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
    $out = '';
    foreach ([false => 'logo-light', true => 'logo-dark'] as $dark => $themeClass) {
        $url = brandLogoUrl((bool)$dark);
        // The shipped logos are square tiles; an uploaded one can be any
        // shape, so CSS shows it whole (contain, no tile) via .brand-custom.
        $custom = strpos($url, BRAND_URLBASE) === 0 ? ' brand-custom' : '';
        $out .= '<img class="' . $class . ' ' . $themeClass . $custom . '" src="'
            . htmlspecialchars($base . $url, ENT_QUOTES, 'UTF-8') . '" alt="' . $alt . '">';
    }
    return $out;
}
