<?php

/**
 * PayMongo — Checkout Sessions integration.
 *
 * Why a thin client instead of the paymongo/paymongo-php SDK: that package
 * has a single v0.0.0 release, was last pushed in Aug 2023, and its
 * ServiceFactory exposes only links/customers/payments/paymentIntents/
 * paymentMethods/refunds/sources/webhooks — it has no Checkout Session
 * support at all. Checkout Sessions are the flow we want (PayMongo hosts the
 * payment page, so card data never touches this server), so this file speaks
 * to the REST API directly with curl, the same way the Gemini and reCAPTCHA
 * calls in this codebase already do.
 *
 * MONEY RULE: PayMongo works in CENTAVOS (integers). Every peso amount is
 * converted exactly once, here, and always from the database — never from a
 * form field. See paymongoCentavos().
 *
 * Requires includes/config.php first (for $conn and the .env loader).
 */

if (!defined('PAYMONGO_API_BASE')) {

    define('PAYMONGO_API_BASE', 'https://api.paymongo.com/v1');

    /** Live keys start with sk_live_ / pk_live_; test keys with sk_test_. */
    define('PAYMONGO_SECRET_KEY',     getenv('PAYMONGO_SECRET_KEY')     ?: '');
    define('PAYMONGO_PUBLIC_KEY',     getenv('PAYMONGO_PUBLIC_KEY')     ?: '');
    define('PAYMONGO_WEBHOOK_SECRET', getenv('PAYMONGO_WEBHOOK_SECRET') ?: '');

    /**
     * Channels offered on the hosted page. 'card' also covers debit cards.
     * Keep this in sync with what is actually enabled on the PayMongo
     * dashboard — asking for a channel the account cannot accept makes the
     * whole checkout_sessions call fail with a 400.
     */
    define('PAYMONGO_METHODS', getenv('PAYMONGO_METHODS') ?: 'gcash,paymaya,card,grab_pay');

    /** How long a created session stays usable before we re-create it. */
    define('PAYMONGO_SESSION_TTL_MINUTES', 60);

    // -----------------------------------------------------------------
    // Configuration
    // -----------------------------------------------------------------

    /** True when a secret key is present, so callers can degrade gracefully. */
    function paymongoIsConfigured(): bool
    {
        return PAYMONGO_SECRET_KEY !== '';
    }

    /** True when the configured key is a sandbox/test key. */
    function paymongoIsTestMode(): bool
    {
        return strpos(PAYMONGO_SECRET_KEY, 'sk_test_') === 0;
    }

    /**
     * Absolute site root, for the success/cancel URLs PayMongo redirects to.
     *
     * APP_URL wins when set — that is the only value that survives a deploy
     * behind a proxy. The request-derived fallback keeps XAMPP working with
     * no configuration, and is host-allowlisted so a forged Host header
     * cannot turn our own return URL into somebody else's site.
     */
    function paymongoBaseUrl(): string
    {
        $configured = trim((string) (getenv('APP_URL') ?: ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $hostOnly = strtolower(explode(':', $host)[0]);
        if (!in_array($hostOnly, ['localhost', '127.0.0.1'], true)) {
            $host = 'localhost';
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        // dirname() answers "." for a path with no directory part (and for CLI,
        // where SCRIPT_NAME is not a URL at all). Appending that would build
        // "http://localhost." — a host that does not resolve.
        $dir  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $path = ($dir === '.' || $dir === '/' || $dir === '') ? '' : rtrim($dir, '/');

        return $scheme . '://' . $host . $path;
    }

    /** The payment method types to offer, as a clean list. */
    function paymongoMethods(): array
    {
        $out = [];
        foreach (explode(',', PAYMONGO_METHODS) as $m) {
            $m = trim($m);
            if ($m !== '') {
                $out[] = $m;
            }
        }
        return $out ?: ['gcash', 'card'];
    }

    // -----------------------------------------------------------------
    // Money
    // -----------------------------------------------------------------

    /**
     * Pesos -> centavos.
     *
     * round() and not (int) on purpose: (int)(20.29 * 100) is 2028 on IEEE
     * floats, which would undercharge by a centavo and make the line-item
     * total disagree with the order total.
     */
    function paymongoCentavos(float $peso): int
    {
        return (int) round($peso * 100);
    }

    /** Centavos -> pesos, for display. */
    function paymongoPesos(int $centavos): float
    {
        return $centavos / 100;
    }

    // -----------------------------------------------------------------
    // Transport
    // -----------------------------------------------------------------

    /**
     * One REST call to PayMongo.
     *
     * Never throws: every failure comes back as ok=false with a message safe
     * to log. Callers decide what the customer sees.
     *
     * @return array{ok:bool, status:int, data:array, error:string}
     */
    function paymongoRequest(string $method, string $path, ?array $payload = null): array
    {
        if (!paymongoIsConfigured()) {
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'PayMongo secret key is not configured.'];
        }

        $ch = curl_init();
        $opts = [
            CURLOPT_URL            => PAYMONGO_API_BASE . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                // PayMongo uses HTTP Basic with the secret key as the username
                // and an empty password.
                'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ];
        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $opts);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cErr   = curl_error($ch);
        curl_close($ch);

        if ($body === false || $cErr !== '') {
            error_log('[paymongo] transport: ' . $cErr);
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'Could not reach PayMongo. ' . $cErr];
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            error_log('[paymongo] non-JSON response (HTTP ' . $status . '): ' . mb_substr((string) $body, 0, 300));
            return ['ok' => false, 'status' => $status, 'data' => [], 'error' => 'PayMongo returned an unreadable response.'];
        }

        if ($status < 200 || $status >= 300) {
            // PayMongo errors arrive as { errors: [ { detail, code, ... } ] }
            $details = [];
            foreach ($decoded['errors'] ?? [] as $e) {
                if (!empty($e['detail'])) {
                    $details[] = (string) $e['detail'];
                }
            }
            $msg = $details ? implode(' ', $details) : ('PayMongo error (HTTP ' . $status . ')');
            error_log('[paymongo] ' . $method . ' ' . $path . ' -> ' . $status . ': ' . $msg);
            return ['ok' => false, 'status' => $status, 'data' => $decoded, 'error' => $msg];
        }

        return ['ok' => true, 'status' => $status, 'data' => $decoded, 'error' => ''];
    }

    // -----------------------------------------------------------------
    // Checkout Sessions
    // -----------------------------------------------------------------

    /**
     * Create a hosted checkout session.
     *
     * $lineItems entries: ['name' => string, 'amount' => centavos int,
     *                      'quantity' => int, 'description' => string|null]
     *
     * @return array{ok:bool, id:?string, url:?string, error:string}
     */
    function paymongoCreateCheckoutSession(
        array $lineItems,
        string $successUrl,
        string $cancelUrl,
        string $referenceNumber,
        string $description,
        array $billing = []
    ): array {
        $items = [];
        foreach ($lineItems as $li) {
            $qty = max(1, (int) ($li['quantity'] ?? 1));
            $items[] = array_filter([
                'currency'    => 'PHP',
                'amount'      => (int) $li['amount'],
                'name'        => mb_substr((string) $li['name'], 0, 120),
                'quantity'    => $qty,
                'description' => isset($li['description']) ? mb_substr((string) $li['description'], 0, 200) : null,
            ], static fn($v) => $v !== null);
        }

        $attributes = [
            'line_items'           => $items,
            'payment_method_types' => paymongoMethods(),
            'success_url'          => $successUrl,
            'cancel_url'           => $cancelUrl,
            'reference_number'     => mb_substr($referenceNumber, 0, 60),
            'description'          => mb_substr($description, 0, 200),
            'send_email_receipt'   => true,
            'show_description'     => true,
            'show_line_items'      => true,
        ];

        // Prefilling billing is optional; only send keys that have values so a
        // blank profile field never turns into a validation error.
        $billing = array_filter([
            'name'  => $billing['name']  ?? null,
            'email' => $billing['email'] ?? null,
            'phone' => $billing['phone'] ?? null,
        ], static fn($v) => is_string($v) && trim($v) !== '');
        if ($billing !== []) {
            $attributes['billing'] = $billing;
        }

        $res = paymongoRequest('POST', '/checkout_sessions', ['data' => ['attributes' => $attributes]]);
        if (!$res['ok']) {
            return ['ok' => false, 'id' => null, 'url' => null, 'error' => $res['error']];
        }

        $id  = $res['data']['data']['id'] ?? null;
        $url = $res['data']['data']['attributes']['checkout_url'] ?? null;
        if (!is_string($id) || !is_string($url)) {
            return ['ok' => false, 'id' => null, 'url' => null, 'error' => 'PayMongo did not return a checkout URL.'];
        }
        return ['ok' => true, 'id' => $id, 'url' => $url, 'error' => ''];
    }

    /**
     * Read a session back from PayMongo.
     *
     * This is the ONLY thing that may decide an order is paid. The browser
     * coming back to success_url proves nothing — anyone can type that URL.
     *
     * @return array{ok:bool, paid:bool, amount:int, paymentId:?string,
     *               method:?string, status:?string, error:string}
     */
    function paymongoRetrieveCheckoutSession(string $sessionId): array
    {
        $blank = ['ok' => false, 'paid' => false, 'amount' => 0, 'paymentId' => null,
                  'method' => null, 'status' => null, 'error' => ''];

        $res = paymongoRequest('GET', '/checkout_sessions/' . rawurlencode($sessionId));
        if (!$res['ok']) {
            return $blank + ['error' => $res['error']];
        }

        $attr = $res['data']['data']['attributes'] ?? [];
        return paymongoReadSessionAttributes($attr);
    }

    /**
     * Pull the "is this paid?" answer out of a checkout_session attributes
     * blob. Shared by the return page and the webhook so both judge a payment
     * by exactly the same rule.
     *
     * @return array{ok:bool, paid:bool, amount:int, paymentId:?string,
     *               method:?string, status:?string, error:string}
     */
    function paymongoReadSessionAttributes(array $attr): array
    {
        $paid      = false;
        $amount    = 0;
        $paymentId = null;
        $method    = null;

        // A session carries its payments under payments[] (newer API responses
        // also expose payment_intent.attributes.payments).
        $payments = $attr['payments'] ?? ($attr['payment_intent']['attributes']['payments'] ?? []);
        if (is_array($payments)) {
            foreach ($payments as $p) {
                $pAttr = $p['attributes'] ?? [];
                if (($pAttr['status'] ?? '') === 'paid') {
                    $paid      = true;
                    $amount    = (int) ($pAttr['amount'] ?? 0);
                    $paymentId = isset($p['id']) ? (string) $p['id'] : null;
                    $method    = $pAttr['source']['type'] ?? ($pAttr['payment_method_used'] ?? null);
                    break;
                }
            }
        }

        // Fallback: some responses only set the session-level payment_intent
        // status. Treat 'succeeded' as paid, and take the amount from there.
        if (!$paid) {
            $piStatus = $attr['payment_intent']['attributes']['status'] ?? null;
            if ($piStatus === 'succeeded') {
                $paid   = true;
                $amount = (int) ($attr['payment_intent']['attributes']['amount'] ?? 0);
            }
        }

        return [
            'ok'        => true,
            'paid'      => $paid,
            'amount'    => $amount,
            'paymentId' => $paymentId,
            'method'    => is_string($method) ? $method : null,
            'status'    => isset($attr['status']) ? (string) $attr['status'] : null,
            'error'     => '',
        ];
    }

    // -----------------------------------------------------------------
    // Webhook signature
    // -----------------------------------------------------------------

    /**
     * Verify a PayMongo webhook signature.
     *
     * Header shape: "t=<unix>,te=<sig>,li=<sig>"  (te = test, li = live).
     * The signed payload is "<t>.<raw body>", HMAC-SHA256 with the webhook
     * secret. Compared with hash_equals so the check is constant-time.
     *
     * Returns false when no webhook secret is set: an unverifiable webhook
     * must never be trusted to mark an order paid.
     */
    function paymongoVerifyWebhookSignature(string $rawBody, string $signatureHeader, int $toleranceSeconds = 300): bool
    {
        if (PAYMONGO_WEBHOOK_SECRET === '' || $signatureHeader === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $chunk) {
            $kv = explode('=', trim($chunk), 2);
            if (count($kv) === 2) {
                $parts[trim($kv[0])] = trim($kv[1]);
            }
        }

        $timestamp = $parts['t'] ?? '';
        $supplied  = paymongoIsTestMode() ? ($parts['te'] ?? '') : ($parts['li'] ?? '');
        if ($timestamp === '' || $supplied === '') {
            return false;
        }

        // Reject replays of an old, already-captured webhook body.
        if (abs(time() - (int) $timestamp) > $toleranceSeconds) {
            error_log('[paymongo] webhook timestamp outside tolerance');
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, PAYMONGO_WEBHOOK_SECRET);
        return hash_equals($expected, $supplied);
    }

    // -----------------------------------------------------------------
    // Local session bookkeeping
    // -----------------------------------------------------------------

    /** True once migrate_paymongo.sql has been applied. */
    function paymongoTableExists(): bool
    {
        global $conn;
        static $exists = null;
        if ($exists === null) {
            $r = $conn->query("SHOW TABLES LIKE 'paymongo_sessions'");
            $exists = $r && $r->num_rows > 0;
        }
        return $exists;
    }

    /**
     * Remember a session we just created, so the return page and the webhook
     * can both map a PayMongo id back to one of our orders.
     */
    function paymongoRecordSession(string $sessionId, string $kind, int $orderId, int $userId, float $amount, string $checkoutUrl): bool
    {
        global $conn;
        if (!paymongoTableExists()) {
            return false;
        }
        $stmt = $conn->prepare(
            "INSERT INTO paymongo_sessions (session_id, order_kind, order_id, user_id, amount, status, checkout_url)
             VALUES (?, ?, ?, ?, ?, 'created', ?)
             ON DUPLICATE KEY UPDATE checkout_url = VALUES(checkout_url), updated_at = CURRENT_TIMESTAMP"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssiids', $sessionId, $kind, $orderId, $userId, $amount, $checkoutUrl);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /** The stored row for a PayMongo session id, or null. */
    function paymongoFindSession(string $sessionId): ?array
    {
        global $conn;
        if (!paymongoTableExists()) {
            return null;
        }
        $stmt = $conn->prepare("SELECT * FROM paymongo_sessions WHERE session_id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * The newest still-usable session for an order, so a customer who bounces
     * back to checkout is sent to the SAME PayMongo page instead of piling up
     * abandoned sessions.
     */
    function paymongoReusableSession(string $kind, int $orderId): ?array
    {
        global $conn;
        if (!paymongoTableExists()) {
            return null;
        }
        $stmt = $conn->prepare(
            "SELECT * FROM paymongo_sessions
              WHERE order_kind = ? AND order_id = ? AND status = 'created'
                AND created_at > (NOW() - INTERVAL ? MINUTE)
              ORDER BY id DESC LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $ttl = PAYMONGO_SESSION_TTL_MINUTES;
        $stmt->bind_param('sii', $kind, $orderId, $ttl);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /** Record the outcome of a session. */
    function paymongoUpdateSessionStatus(string $sessionId, string $status, ?string $paymentId = null, ?string $methodUsed = null, ?array $raw = null): void
    {
        global $conn;
        if (!paymongoTableExists()) {
            return;
        }
        $rawJson = $raw === null ? null : json_encode($raw, JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare(
            "UPDATE paymongo_sessions
                SET status = ?, payment_id = ?, payment_method_used = ?, raw_payload = ?
              WHERE session_id = ?"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('sssss', $status, $paymentId, $methodUsed, $rawJson, $sessionId);
        $stmt->execute();
        $stmt->close();
    }
}
