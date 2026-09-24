<?php
declare(strict_types=1);

/**
 * Manual QR Payment — configuration & shared business logic.
 *
 * Pure logic only: no HTML output here. Safe to require from page
 * controllers, the admin panel, CLI scripts, and the chatbot hook.
 *
 * Requires includes/config.php to have been loaded first (for $conn and
 * the CSRF/sanitize helpers).
 */

// ---------------------------------------------------------------------
// Constants (no magic numbers scattered through the code)
// ---------------------------------------------------------------------

/** Absolute path to the protected proof storage root (NOT web-browsable). */
define('PAYMENT_PROOF_STORAGE', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'payment-proofs');

/** Admin-editable channel config (JSON). Lives under /storage (protected). */
define('PAYMENT_QR_CONFIG_FILE', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'payment-config' . DIRECTORY_SEPARATOR . 'qr-channels.json');

/** Public folder + URL base where merchant "receive" QR images live. */
define('PAYMENT_QR_IMAGE_DIR', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'payment-qr');
define('PAYMENT_QR_IMAGE_URLBASE', 'images/payment-qr/');

/** Upload limits / allow-list for the customer proof screenshot. */
define('PAYMENT_PROOF_MAX_BYTES', 5 * 1024 * 1024); // 5 MB
/** Real (sniffed) MIME types we accept, mapped to a normalised extension. */
const PAYMENT_PROOF_ALLOWED = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

/**
 * Channels this system understands. Display data comes from the JSON config.
 *
 * Adding a key here surfaces it in admin Payment Settings, on payment-qr.php
 * and in the custom-order payment page automatically — but the two
 * payment_method ENUM columns must accept it too, so a new key also needs a
 * migration (see migrate_bank_transfer.sql).
 */
const PAYMENT_CHANNELS = ['instapay', 'gcash', 'maya', 'bdo', 'bpi', 'securitybank', 'rcbc'];

/** Bank channels settle by account transfer, so a QR image is optional for them. */
const PAYMENT_BANK_CHANNELS = ['bdo', 'bpi', 'securitybank', 'rcbc'];

/** Retention: delete proofs this many days after an order is completed/cancelled. */
define('PAYMENT_PROOF_RETENTION_DAYS', (int)(getenv('PAYMENT_PROOF_RETENTION_DAYS') ?: 90));

// ---------------------------------------------------------------------
// QR channel configuration (admin-editable JSON)
// ---------------------------------------------------------------------

/**
 * Default channel config used when no JSON file exists yet. The InstaPay
 * channel ships enabled and points at the bundled default QR image so the
 * flow is demonstrable out of the box.
 *
 * @return array<string,array<string,mixed>>
 */
function paymentDefaultChannels(): array
{
    return [
        'instapay' => [
            'enabled'      => true,
            'display_name' => 'InstaPay (Scan any bank/e-wallet app)',
            'account_name' => 'Thread & Press Hub',
            'account_no'   => '—',
            'qr_image'     => 'instapay-default.png',
        ],
        'gcash' => [
            'enabled'      => false,
            'display_name' => 'GCash',
            'account_name' => 'Thread & Press Hub',
            'account_no'   => '09XX-XXX-XXXX',
            'qr_image'     => '',
        ],
        'maya' => [
            'enabled'      => false,
            'display_name' => 'Maya',
            'account_name' => 'Thread & Press Hub',
            'account_no'   => '09XX-XXX-XXXX',
            'qr_image'     => '',
        ],
        // Bank transfers. Ship disabled with placeholder account numbers so a
        // channel cannot go live before an admin has entered real details.
        'bdo' => [
            'enabled'      => false,
            'display_name' => 'BDO Bank Transfer',
            'account_name' => 'Thread & Press Hub',
            'account_no'   => '0000-0000-0000',
            'qr_image'     => '',
        ],
        'bpi' => [
            'enabled'      => false,
            'display_name' => 'BPI Bank Transfer',
            'account_name' => 'Thread & Press Hub',
            'account_no'   => '0000-0000-0000',
            'qr_image'     => '',
        ],
        'securitybank' => [
            'enabled'      => false,
            'display_name' => 'Security Bank Transfer',
            'account_name' => 'Thread & Press Hub',
            'account_no'   => '0000-0000-0000',
            'qr_image'     => '',
        ],
        'rcbc' => [
            'enabled'      => false,
            'display_name' => 'RCBC Bank Transfer',
            'account_name' => 'Thread & Press Hub',
            'account_no'   => '0000-0000-0000',
            'qr_image'     => '',
        ],
    ];
}

/** True for channels that settle by account transfer rather than by QR scan. */
function paymentChannelIsBank(string $key): bool
{
    return in_array($key, PAYMENT_BANK_CHANNELS, true);
}

/**
 * Short title for a channel key, for admin cards and headings.
 * ucfirst() alone produced "Bdo", "Bpi", "Securitybank", "Rcbc".
 */
function paymentChannelTitle(string $key): string
{
    $titles = [
        'instapay'     => 'InstaPay',
        'gcash'        => 'GCash',
        'maya'         => 'Maya',
        'bdo'          => 'BDO',
        'bpi'          => 'BPI',
        'securitybank' => 'Security Bank',
        'rcbc'         => 'RCBC',
    ];
    return $titles[$key] ?? ucfirst($key);
}

/**
 * Human label for a stored payment_method value.
 *
 * Display code used to test `=== 'gcash'` and call everything else Cash on
 * Delivery, which would mislabel every bank transfer once banks became a
 * stored value. Resolving through the channel config keeps the label right
 * for any channel that is ever added.
 */
function paymentMethodLabel(?string $method): string
{
    $method = (string) $method;
    if ($method === '' ) {
        return 'Not specified';
    }
    if ($method === 'cod') {
        return 'Cash on Delivery';
    }
    // Settled by the PayMongo gateway. It is not a QR channel, so it has no
    // display_name in the JSON config and would otherwise print as "Paymongo".
    if ($method === 'paymongo') {
        return 'Paid online (PayMongo)';
    }
    $channels = paymentGetChannels();
    if (isset($channels[$method]['display_name']) && $channels[$method]['display_name'] !== '') {
        return (string) $channels[$method]['display_name'];
    }
    return ucfirst(str_replace('_', ' ', $method));
}

/**
 * Load the channel configuration, merged over the defaults so a partial or
 * missing file never produces an undefined-key error.
 *
 * @return array<string,array<string,mixed>>
 */
function paymentGetChannels(): array
{
    $channels = paymentDefaultChannels();

    if (is_readable(PAYMENT_QR_CONFIG_FILE)) {
        $raw = file_get_contents(PAYMENT_QR_CONFIG_FILE);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            foreach (PAYMENT_CHANNELS as $key) {
                if (isset($decoded[$key]) && is_array($decoded[$key])) {
                    $channels[$key] = array_merge($channels[$key], $decoded[$key]);
                }
            }
        }
    }

    return $channels;
}

/**
 * Persist the channel configuration as pretty JSON.
 *
 * @param array<string,array<string,mixed>> $channels
 * @return bool True on success.
 */
function paymentSaveChannels(array $channels): bool
{
    $dir = dirname(PAYMENT_QR_CONFIG_FILE);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }
    $json = json_encode($channels, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    return file_put_contents(PAYMENT_QR_CONFIG_FILE, $json, LOCK_EX) !== false;
}

/**
 * Only the channels an admin has switched on, for display at checkout.
 *
 * @return array<string,array<string,mixed>>
 */
function paymentEnabledChannels(): array
{
    return array_filter(paymentGetChannels(), static fn(array $c): bool => !empty($c['enabled']));
}

// ---------------------------------------------------------------------
// Display helpers
// ---------------------------------------------------------------------

/** Format a peso amount for display, e.g. 1949 -> "₱1,949.00". */
function paymentFormatPeso(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

// ---------------------------------------------------------------------
// Duplicate-reference detection (anti "one receipt, many orders")
// ---------------------------------------------------------------------

/**
 * Find OTHER orders that already used the same payment reference number.
 * Used by the admin panel to block reusing a single receipt across orders.
 *
 * @return array<int> Distinct order IDs (excluding $excludeOrderId) that
 *                     already claimed this reference in a live submission.
 */
function paymentReferenceUsedElsewhere(mysqli $conn, string $reference, int $excludeOrderId): array
{
    $reference = trim($reference);
    if ($reference === '') {
        return [];
    }
    $stmt = $conn->prepare(
        "SELECT DISTINCT order_id
           FROM payment_submissions
          WHERE reference_number = ?
            AND order_id <> ?
            AND status IN ('pending_verification','verified')
          ORDER BY order_id"
    );
    $stmt->bind_param('si', $reference, $excludeOrderId);
    $stmt->execute();
    $res = $stmt->get_result();
    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int)$row['order_id'];
    }
    $stmt->close();
    return $ids;
}

// ---------------------------------------------------------------------
// Chatbot hook — read-only payment status for a given order
// ---------------------------------------------------------------------

/**
 * Internal API for the (separately-built) chatbot module.
 *
 * Returns a small, stable structure describing an order's payment state.
 * Does NOT expose proof images or personal data — status only.
 *
 * @return array{found:bool, code:string, label:string, reason:?string}
 *   code is one of: not_found | pending | awaiting_verification | paid | rejected
 *
 * @example
 *   require_once __DIR__.'/includes/payment-config.php';
 *   $status = getPaymentStatus(28); // ['code'=>'awaiting_verification', ...]
 */
function getPaymentStatus(int $orderId): array
{
    global $conn;

    $stmt = $conn->prepare("SELECT payment_status FROM orders WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        return ['found' => false, 'code' => 'not_found', 'label' => 'Order not found', 'reason' => null];
    }

    switch ($order['payment_status']) {
        case 'verified':
            return ['found' => true, 'code' => 'paid', 'label' => 'Paid', 'reason' => null];

        case 'pending_verification':
            return ['found' => true, 'code' => 'awaiting_verification', 'label' => 'Awaiting Verification', 'reason' => null];

        case 'rejected':
            // Surface the latest rejection reason so the bot can relay it.
            $reason = null;
            $r = $conn->prepare(
                "SELECT reject_reason FROM payment_submissions
                  WHERE order_id = ? AND status = 'rejected'
                  ORDER BY reviewed_at DESC, id DESC LIMIT 1"
            );
            $r->bind_param('i', $orderId);
            $r->execute();
            if ($row = $r->get_result()->fetch_assoc()) {
                $reason = $row['reject_reason'] !== '' ? $row['reject_reason'] : null;
            }
            $r->close();
            return ['found' => true, 'code' => 'rejected', 'label' => 'Rejected', 'reason' => $reason];

        case 'unpaid':
        default:
            return ['found' => true, 'code' => 'pending', 'label' => 'Pending', 'reason' => null];
    }
}

/*
 * -----------------------------------------------------------------------
 * CHATBOT MOUNT POINT
 * -----------------------------------------------------------------------
 * The existing chatbot module (js/chatbot.js + its PHP endpoint) can call
 * getPaymentStatus($orderId) above to answer "where's my payment?" queries.
 * To wire it up later, add a small intent handler in the chatbot endpoint:
 *
 *   require_once __DIR__ . '/includes/payment-config.php';
 *   $s = getPaymentStatus($orderId);
 *   echo $s['label'] . ($s['reason'] ? ' — ' . $s['reason'] : '');
 *
 * Do NOT build chatbot UI/logic here; this is only the read API + note.
 * -----------------------------------------------------------------------
 */
